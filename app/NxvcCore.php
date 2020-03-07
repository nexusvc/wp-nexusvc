<?php

namespace App;

class NxvcCore {

    public static function has($cmd, $error = true) {
        
        switch($cmd) {
            case 'gravity forms':
                if(class_exists('GFForms') && \GFForms::$version && \is_plugin_active('gravityforms/gravityforms.php')) {
                    return 'Installed';
                }
                break;
        }

        $has = shell_exec(sprintf("which %s", escapeshellarg($cmd)));
        if(empty($has) && $error) return nexusvcError("You must install <code>{$cmd}</code> on your system.", "{$cmd}");
        if(empty($has) && !$error) return 'Not Installed';
        if(!empty($has) && !$error) return 'Installed';
    }

    public static function hasComposer() {
        return NxvcCore::has('composer');
    }

    public static function hasPhp() {
        return NxvcCore::has('php');
    }

    public static function hasSupervisor() {
        return NxvcCore::has('supervisord');
    }

    public static function status() {

    }

    public static function version($cmd) {
        switch($cmd) {
            case 'gravity forms':
                if(class_exists('GFForms')) return \GFForms::$version;
                break;
            case 'composer':
                return shell_exec("composer --version | awk '{ print $3 }'");
                break;
            case 'python':
                if(empty(shell_exec(sprintf("which %s", escapeshellarg($cmd))))) {
                    return static::version('python3');
                }
                return shell_exec("python --version | awk '{ print $2 }'");
                break;
            case 'python3':
                return shell_exec("python3 --version | awk '{ print $2 }'");
                break;
            case 'php':
                return shell_exec("php -v | grep ^PHP | cut -d' ' -f2");
                break;
            default:
                return shell_exec("{$cmd} --version");
        }
    }

}
