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
		// Check if version is allowed in current context.
		$config = self::get_context_config_static();
		if ( ! $config['allow_version'] ) {
			Console::error( 'Version command is not allowed in this context.' );
			Console::line();
			Console::comment( 'Current context: ' . $config['message'] );
			if ( isset( $config['name'] ) ) {
				Console::comment( 'Project name: ' . $config['name'] );
			}
			Console::line();
			return 1;
		}

		$base            = self::base_path();
		$current_version = $this->detect_current_version( $base );
		if ( ! $current_version ) {
			Console::error( 'Could not determine current version from composer.json, readme.txt, or plugin file.' );
			return 1; }

		$options = $this->parse_version_arguments( $args );

		// If no explicit version or bump type specified, just show current version.
		if ( ! $options['explicit'] && ! $options['bump'] ) {
			Console::line();
			Console::info( 'Current version: ' . $current_version );
			Console::line();

			// Offer interactive mode if user wants to bump version.
			Console::comment( 'Run with --bump to update version interactively, or specify --bump=[patch|minor|major]' );
			Console::line();
			return 0;
		}

		// If --bump flag is specified without value, run interactive mode.
		if ( $options['bump'] === true || $options['bump'] === 'interactive' ) {
			return $this->run_interactive_mode( $current_version, $base );
		}

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

	/**
	 * Run the version command in interactive mode.
	 *
	 * @param string $current_version Current version.
	 * @param string $base_path Base path.
	 * @return int Exit status.
	 */
	private function run_interactive_mode( $current_version, $base_path ) {
		Console::line();
		Console::info( 'Welcome to Version Updater!' );
		Console::line();
		Console::comment( 'Current version: ' . $current_version );
		Console::line();

		// Calculate possible new versions.
		$major_version = $this->bump_semver( $current_version, 'major' );
		$minor_version = $this->bump_semver( $current_version, 'minor' );
		$patch_version = $this->bump_semver( $current_version, 'patch' );

		Console::comment( 'Select the type of version bump:' );
		Console::line( '  1) Patch: ' . $current_version . ' → ' . $patch_version . ' (patches and small fixes)' );
		Console::line( '  2) Minor: ' . $current_version . ' → ' . $minor_version . ' (new features, backward compatible)' );
		Console::line( '  3) Major: ' . $current_version . ' → ' . $major_version . ' (breaking changes)' );
		Console::line( '  4) Enter custom version' );
		Console::line();

		// Get user input.
		$handle = fopen( 'php://stdin', 'r' );
		Console::comment( 'Choose an option (1-4): ' );
		$input = trim( fgets( $handle ) );

		$selected_version = null;

		switch ( $input ) {
			case '1':
				$selected_version = $patch_version;
				break;
			case '2':
				$selected_version = $minor_version;
				break;
			case '3':
				$selected_version = $major_version;
				break;
			case '4':
				Console::comment( 'Enter new version (e.g., 1.2.3): ' );
				$custom_version = trim( fgets( $handle ) );

				if ( ! $this->is_valid_semver( $custom_version ) ) {
					fclose( $handle );
					Console::error( 'Invalid version format. Expected semantic version x.y.z.' );
					return 1;
				}
				$selected_version = $custom_version;
				break;
			default:
				fclose( $handle );
				Console::error( 'Invalid option selected. Please choose 1, 2, 3, or 4.' );
				return 1;
		}

		// Confirm the change.
		Console::line();
		Console::warning( 'You are about to update from ' . $current_version . ' to ' . $selected_version );
		Console::comment( 'Continue? (y/N): ' );
		$handle_confirm = fopen( 'php://stdin', 'r' );
		$confirmation   = trim( strtolower( fgets( $handle_confirm ) ) );
		fclose( $handle_confirm );

		if ( ! in_array( $confirmation, array( 'y', 'yes', '1' ), true ) ) {
			Console::info( 'Operation cancelled.' );
			return 0;
		}

		return $this->update_to_version( $current_version, $selected_version, $base_path, false );
	}

	/**
	 * Update to specified version.
	 *
	 * @param string $current_version Current version.
	 * @param string $new_version New version to update to.
	 * @param string $base_path Base path.
	 * @param bool $dry_run Dry run flag.
	 * @return int Exit status.
	 */
	private function update_to_version( $current_version, $new_version, $base_path, $dry_run ) {
		Console::line();
		Console::comment( 'Updating WPMoo version: ' . $current_version . ' → ' . $new_version );

		$updated_files = $this->update_version_files( $base_path, $current_version, $new_version, $dry_run );

		if ( empty( $updated_files ) ) {
			Console::warning( 'No files required updating. Verify project structure.' );
		} else {
			foreach ( $updated_files as $file ) {
				Console::line( ( $dry_run ? '[dry-run] ' : '' ) . '   • ' . $this->relative_path( $file ) );
			}
		}

		if ( $dry_run ) {
			Console::info( 'Dry run completed. No files were modified.' );
			Console::line();
			return 0;
		}

		Console::info( 'Version updated successfully to ' . $new_version );

		$this->do_action_safe(
			'wpmoo_cli_version_completed',
			$current_version,
			$new_version,
			array(
				'files'   => $updated_files,
				'options' => array( 'dry-run' => $dry_run ),
			)
		);

		Console::line();
		return 0;
	}
}
