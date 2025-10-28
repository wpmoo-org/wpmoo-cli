<?php

namespace WPMoo\CLI;

class Console {
	public static function info( $message ) {
		echo "\033[32m{$message}\033[0m" . PHP_EOL; }
	public static function banner( $message ) {
		echo "\033[35;1m{$message}\033[0m" . PHP_EOL; }
	public static function error( $message ) {
		echo "\033[31m{$message}\033[0m" . PHP_EOL; }
	public static function warning( $message ) {
		echo "\033[33m{$message}\033[0m" . PHP_EOL; }
	public static function comment( $message ) {
		echo "\033[36m{$message}\033[0m" . PHP_EOL; }
	public static function line( $message = '' ) {
		echo $message . PHP_EOL; }
}
