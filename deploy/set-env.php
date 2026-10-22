<?php

/*
| Set values in Cloyne's .env file safely, whatever characters they contain.
| Usage:  php deploy/set-env.php KEY=value OTHER=@ENV_VAR_NAME
| "@NAME" reads the value from an environment variable, so passwords never
| appear on the command line or in the shell history.
*/

$file = dirname(__DIR__).'/.env';
$env = is_file($file) ? file_get_contents($file) : '';

foreach (array_slice($argv, 1) as $pair) {
    [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
    if (str_starts_with($value, '@')) {
        $value = (string) getenv(substr($value, 1));
    }

    // Plain values stay bare; anything with spaces or symbols is quoted the way .env files expect.
    if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@+,-]+$/', $value)) {
        $line = "{$key}={$value}";
    } elseif (! str_contains($value, "'")) {
        $line = "{$key}='{$value}'";
    } else {
        $line = $key.'="'.addcslashes($value, "\"\\$").'"';
    }

    $pattern = '/^#?\s*'.preg_quote($key, '/').'=.*$/m';
    $env = preg_match($pattern, $env)
        ? preg_replace_callback($pattern, fn () => $line, $env, 1)
        : rtrim($env)."\n{$line}\n";
}

file_put_contents($file, $env);
