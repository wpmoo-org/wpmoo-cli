<?php
/**
 * Version command for the WPMoo CLI.
 *
 * Updates project version across relevant files (plugin header, readme.txt, etc.).
 *
 * @package WPMoo\CLI\Commands
 * @since 0.1.0
 * @link  https://wpmoo.org WPMoo – WordPress Micro Object-Oriented Framework.
 * @link  https://github.com/wpmoo/wpmoo GitHub Repository.
 * @license https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 */

namespace WPMoo\CLI\Commands;

use WPMoo\CLI\Contracts\CommandInterface;
use WPMoo\CLI\Support\Base;
use WPMoo\CLI\Console;

/**
 * Version command to update project version across manifests.
 */
class VersionCommand extends Base implements CommandInterface {
	/**
	 * Handle the version command.
	 *
	 * @param array<int, mixed> $args Command arguments.
	 * @return int Exit status (0 for success, non-zero for failure).
	 */
	public function handle( array $args = array() ) {
		$base            = self::base_path();
		$current_version = $this->detect_current_version( $base );
		if ( ! $current_version ) {
			Console::error( 'Could not determine current version from composer.json.' );
			return 1; }
		$options           = $this->parse_version_arguments( $args );
		$requested_version = null;
		if ( $options['explicit'] ) {
			$requested_version = $this->sanitize_version_input( $options['explicit'] );
			if ( ! $this->is_valid_semver( $requested_version ) ) {
				Console::error( 'Explicit version "' . $options['explicit'] . '" is not a valid semantic version (expected format x.y.z).' );
				return 1; }
		} else {
			$bump_type         = $options['bump'] ? $options['bump'] : 'patch';
			$requested_version = $this->bump_semver( $current_version, $bump_type, $options['pre-release'] );
			if ( ! $requested_version ) {
				Console::error( 'Unable to compute new version from current value ' . $current_version );
				return 1; }
		}
		if ( $requested_version === $current_version ) {
			Console::comment( 'Version remains unchanged (' . $current_version . '). Nothing to do.' );
			return 0; }
		Console::line();
		Console::comment( 'Updating WPMoo version: ' . $current_version . ' → ' . $requested_version );
		$updated_files = $this->update_version_files( $base, $current_version, $requested_version, $options['dry-run'] );
		if ( empty( $updated_files ) ) {
			Console::warning( 'No files required updating. Verify project structure.' ); } else {
			foreach ( $updated_files as $file ) {
				Console::line( ( $options['dry-run'] ? '[dry-run] ' : '' ) . '   • ' . $this->relative_path( $file ) ); }
			}
			if ( $options['dry-run'] ) {
				Console::info( 'Dry run completed. No files were modified.' );
				Console::line();
				return 0; }
			Console::info( 'Version updated successfully to ' . $requested_version );
			$this->do_action_safe(
				'wpmoo_cli_version_completed',
				$current_version,
				$requested_version,
				array(
					'files'   => $updated_files,
					'options' => $options,
				)
			);
		Console::line();
		return 0;
	}
}
