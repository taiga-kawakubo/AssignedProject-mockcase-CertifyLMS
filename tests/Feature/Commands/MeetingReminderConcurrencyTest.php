<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class MeetingReminderConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public static function mailRecipients(): array
    {
        return [['student'], ['coach']];
    }

    #[DataProvider('mailRecipients')]
    public function test_concurrent_commands_send_each_reminder_mail_only_once(string $recipient): void
    {
        if (! function_exists('pcntl_fork') || DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('並行配信の検証にはpcntlとMySQLが必要です。');
        }

        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $mailColumn = 'eve_'.$recipient.'_email_notified_at';

        $meeting = Meeting::factory()->reserved()->forEnrollment($enrollment)->forCoach($coach)->create([
            'scheduled_at' => now()->addDay()->setTime(10, 0),
            'eve_student_database_notified_at' => now(),
            'eve_coach_database_notified_at' => now(),
            'eve_student_email_notified_at' => $recipient === 'student' ? null : now(),
            'eve_coach_email_notified_at' => $recipient === 'coach' ? null : now(),
        ]);

        $directory = sys_get_temp_dir().'/reminder-concurrency-'.Str::uuid();
        mkdir($directory, 0700);
        $children = [];

        // Act
        DB::disconnect();

        try {
            foreach ([0, 1] as $index) {
                $pid = pcntl_fork();
                if ($pid === -1) {
                    throw new RuntimeException('子プロセスを開始できませんでした。');
                }

                if ($pid === 0) {
                    DB::purge();
                    config(['mail.default' => 'array', 'queue.default' => 'sync']);

                    Event::listen(NotificationSent::class, function (NotificationSent $event) use ($directory, $index): void {
                        if ($event->channel !== 'mail') {
                            return;
                        }

                        file_put_contents($directory.'/mail-'.$index, 'sent'.PHP_EOL, FILE_APPEND);

                        if ($index === 0) {
                            // メール送信後、送信日時を保存する前にもう一方を起動する。
                            $this->waitForFile($directory.'/release');
                        }
                    });

                    try {
                        if ($index === 1) {
                            DB::connection()->beforeExecuting(function (string $sql) use ($directory): void {
                                if (str_contains($sql, '`meetings`') && str_contains($sql, 'for update')) {
                                    touch($directory.'/started');
                                }
                            });
                        }

                        $exitCode = Artisan::call('notifications:send-meeting-reminders', ['--window' => 'eve']);
                        file_put_contents($directory.'/result-'.$index, (string) $exitCode);
                        DB::disconnect();
                        exit($exitCode);
                    } catch (\Throwable $exception) {
                        file_put_contents($directory.'/result-'.$index, $exception->getMessage());
                        exit(1);
                    }
                }

                $children[] = $pid;

                if ($index === 0) {
                    $this->waitForFile($directory.'/mail-0');
                }
            }

            $this->waitForFile($directory.'/started');
            $deadline = microtime(true) + 2;
            while (! is_file($directory.'/result-1') && microtime(true) < $deadline) {
                usleep(10000);
            }

            touch($directory.'/release');

            foreach ($children as $pid) {
                pcntl_waitpid($pid, $status);
                $this->assertTrue(pcntl_wifexited($status));
                $this->assertSame(0, pcntl_wexitstatus($status));
            }
            $children = [];

            // Assert
            $this->assertSame('0', file_get_contents($directory.'/result-0'));
            $this->assertSame('0', file_get_contents($directory.'/result-1'));
            $this->assertSame('sent'.PHP_EOL, file_get_contents($directory.'/mail-0'));
            $this->assertFileDoesNotExist($directory.'/mail-1');
            $this->assertNotNull($meeting->fresh()->{$mailColumn});
        } finally {
            touch($directory.'/release');
            foreach ($children as $pid) {
                pcntl_waitpid($pid, $status);
            }
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    private function waitForFile(string $path): void
    {
        $deadline = microtime(true) + 10;
        while (! is_file($path)) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('配信処理の同期がタイムアウトしました。');
            }
            usleep(10000);
        }
    }
}
