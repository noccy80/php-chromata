<?php

class GlobalLoggerFunc {
    public static function bind() {
        static $init;
        if ($init++ == 0) {
            if (posix_isatty(STDOUT)) 
                self::bind_conlog();
            else
                self::bind_stdlog();
        }
    }
    private static function bind_conlog()
    {
        function l_info($fmt,...$arg)   { printf("\e[32m[i]\e[1m {$fmt}\e[0m\n", ...$arg); }
        function l_debug($fmt,...$arg)  { printf("\e[32m[d] {$fmt}\e[0m\n", ...$arg); }
        function l_error($fmt,...$arg)  { printf("\e[31m[e]\e[1m {$fmt}\e[0m\n", ...$arg); }   
        function l_warn($fmt,...$arg)   { printf("\e[33m[w]\e[1m {$fmt}\e[0m\n", ...$arg); }
        function l_notice($fmt,...$arg)   { printf("\e[33m[n] {$fmt}\e[0m\n", ...$arg); }
    }
    private static function bind_stdlog()
    {
        function l_info($fmt,...$arg)   { fprintf(STDOUT,"{$fmt}\n",...$arg); }
        function l_debug($fmt,...$arg)  { fprintf(STDOUT,"{$fmt}\n",...$arg); }
        function l_error($fmt,...$arg)  { fprintf(STDERR,"error: {$fmt}\n",...$arg); }   
        function l_warn($fmt,...$arg)   { fprintf(STDERR,"warn: {$fmt}\n",...$arg); }
        function l_notice($fmt,...$arg)   { fprintf(STDERR,"notice: {$fmt}\n",...$arg); }
    }
}
if (!defined("STDOUT")) define("STDOUT",null);
GlobalLoggerFunc::bind();
