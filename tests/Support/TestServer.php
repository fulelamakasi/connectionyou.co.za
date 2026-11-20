<?php
    namespace Tests\Support;

    use RuntimeException;

    /**
     * Runs public/ on PHP's built-in server so the endpoints are tested over real HTTP,
     * exactly as the browser calls them. One server per distinct set of env overrides,
     * started on first use and stopped when PHPUnit exits.
     */
    final class TestServer
    {
        /** @var array<string, array{process: resource, url: string, log: string}> */
        private static $servers = [];

        public static function url(array $envOverrides = []): string
        {
            ksort($envOverrides);
            $key = md5(json_encode($envOverrides));

            if (!isset(self::$servers[$key])) {
                self::$servers[$key] = self::start($envOverrides);
            }

            return self::$servers[$key]['url'];
        }

        public static function logTail(array $envOverrides = [], int $lines = 20): string
        {
            ksort($envOverrides);
            $key = md5(json_encode($envOverrides));

            if (!isset(self::$servers[$key]) || !is_file(self::$servers[$key]['log'])) {
                return '';
            }

            $content = file(self::$servers[$key]['log'], FILE_IGNORE_NEW_LINES) ?: [];

            return implode("\n", array_slice($content, -$lines));
        }

        private static function start(array $envOverrides): array
        {
            $port = self::freePort();
            $root = realpath(__DIR__ . '/../../public');
            $log = sys_get_temp_dir() . '/connectionyou-test-server-' . $port . '.log';
            @unlink($log);

            $env = array_merge(getenv(), $envOverrides);

            $command = [
                PHP_BINARY,
                '-d', 'variables_order=EGPCS',
                '-d', 'display_errors=0',
                '-d', 'log_errors=1',
                '-d', 'error_log=' . $log,
                '-S', '127.0.0.1:' . $port,
                '-t', $root,
            ];

            $process = proc_open($command, [
                0 => ['pipe', 'r'],
                1 => ['file', $log . '.access', 'a'],
                2 => ['file', $log . '.access', 'a'],
            ], $pipes, $root, $env);

            if (!is_resource($process)) {
                throw new RuntimeException('Could not start the PHP built-in server.');
            }

            $url = 'http://127.0.0.1:' . $port . '/';
            self::waitUntilListening($port, $process);

            register_shutdown_function(static function () use ($process, $pipes) {
                foreach ($pipes as $pipe) {
                    @fclose($pipe);
                }
                @proc_terminate($process);
                @proc_close($process);
            });

            return ['process' => $process, 'url' => $url, 'log' => $log];
        }

        private static function freePort(): int
        {
            $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);

            if (!$socket) {
                throw new RuntimeException("Could not find a free port: $error");
            }

            $name = stream_socket_get_name($socket, false);
            fclose($socket);

            return (int)substr($name, strrpos($name, ':') + 1);
        }

        private static function waitUntilListening(int $port, $process): void
        {
            $deadline = microtime(true) + 10;

            while (microtime(true) < $deadline) {
                $connection = @fsockopen('127.0.0.1', $port, $errno, $error, 0.2);

                if ($connection) {
                    fclose($connection);
                    return;
                }

                $status = proc_get_status($process);

                if (!$status['running']) {
                    throw new RuntimeException('The PHP built-in server exited during start-up.');
                }

                usleep(50000);
            }

            throw new RuntimeException("The PHP built-in server did not start on port $port.");
        }
    }
