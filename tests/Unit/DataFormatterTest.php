<?php
/**
 * Tests for Deploy_Forge_Data_Formatter class.
 *
 * @package Deploy_Forge
 */

namespace DeployForge\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * Test class for data formatter utilities.
 */
class DataFormatterTest extends TestCase {

	/**
	 * Set up before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		// Mock sanitize_file_name for workflow tests.
		Functions\stubs(
			array(
				'sanitize_file_name' => static function ( $filename ) {
					return preg_replace( '/[^a-zA-Z0-9._-]/', '', $filename );
				},
			)
		);

		// Load the class under test.
		require_once DEPLOY_FORGE_PLUGIN_DIR . 'includes/class-data-formatter.php';
	}

	/**
	 * Test format_repository_for_select with array input.
	 *
	 * @return void
	 */
	public function test_format_repository_for_select_with_array(): void {
		$repo = array(
			'full_name'      => 'owner/repo-name',
			'name'           => 'repo-name',
			'owner'          => array( 'login' => 'owner' ),
			'private'        => true,
			'default_branch' => 'main',
			'description'    => 'Test repository',
		);

		$result = \Deploy_Forge_Data_Formatter::format_repository_for_select( $repo );

		$this->assertSame( 'owner/repo-name', $result['id'] );
		$this->assertSame( 'owner/repo-name', $result['text'] );
		$this->assertSame( 'owner/repo-name', $result['full_name'] );
		$this->assertSame( 'repo-name', $result['name'] );
		$this->assertSame( 'owner', $result['owner'] );
		$this->assertTrue( $result['private'] );
		$this->assertSame( 'main', $result['default_branch'] );
		$this->assertSame( 'Test repository', $result['description'] );
	}

	/**
	 * Test format_repository_for_select with object input.
	 *
	 * @return void
	 */
	public function test_format_repository_for_select_with_object(): void {
		$repo = (object) array(
			'full_name'      => 'owner/repo-name',
			'name'           => 'repo-name',
			'owner'          => (object) array( 'login' => 'owner' ),
			'private'        => false,
			'default_branch' => 'develop',
			'description'    => 'Object repository',
		);

		$result = \Deploy_Forge_Data_Formatter::format_repository_for_select( $repo );

		$this->assertSame( 'owner/repo-name', $result['id'] );
		$this->assertSame( 'owner', $result['owner'] );
		$this->assertFalse( $result['private'] );
		$this->assertSame( 'develop', $result['default_branch'] );
	}

	/**
	 * Test format_repository_for_select with missing fields.
	 *
	 * @return void
	 */
	public function test_format_repository_for_select_with_missing_fields(): void {
		$repo = array();

		$result = \Deploy_Forge_Data_Formatter::format_repository_for_select( $repo );

		$this->assertSame( '', $result['id'] );
		$this->assertSame( '', $result['text'] );
		$this->assertSame( '', $result['name'] );
		$this->assertFalse( $result['private'] );
		$this->assertSame( 'main', $result['default_branch'] );
	}

	/**
	 * Test format_workflow_for_select.
	 *
	 * @return void
	 */
	public function test_format_workflow_for_select(): void {
		$workflow = array(
			'id'    => 12345,
			'name'  => 'Build and Deploy',
			'path'  => '.github/workflows/build.yml',
			'state' => 'active',
		);

		$result = \Deploy_Forge_Data_Formatter::format_workflow_for_select( $workflow );

		$this->assertSame( 12345, $result['id'] );
		$this->assertSame( 'Build and Deploy', $result['name'] );
		$this->assertSame( 'build.yml', $result['filename'] );
		$this->assertSame( '.github/workflows/build.yml', $result['path'] );
		$this->assertSame( 'active', $result['state'] );
	}

	/**
	 * Test format_workflow_for_select with missing name uses filename.
	 *
	 * @return void
	 */
	public function test_format_workflow_uses_filename_when_name_missing(): void {
		$workflow = array(
			'id'   => 12345,
			'path' => '.github/workflows/deploy.yml',
		);

		$result = \Deploy_Forge_Data_Formatter::format_workflow_for_select( $workflow );

		$this->assertSame( 'deploy.yml', $result['name'] );
		$this->assertSame( 'deploy.yml', $result['filename'] );
	}

	/**
	 * Test format_branch_for_select with string input.
	 *
	 * @return void
	 */
	public function test_format_branch_for_select_with_string(): void {
		$result = \Deploy_Forge_Data_Formatter::format_branch_for_select( 'main' );

		$this->assertSame( 'main', $result['name'] );
		$this->assertSame( 'main', $result['label'] );
		$this->assertArrayNotHasKey( 'protected', $result );
	}

	/**
	 * Test format_branch_for_select with array input.
	 *
	 * @return void
	 */
	public function test_format_branch_for_select_with_array(): void {
		$branch = array(
			'name'      => 'develop',
			'protected' => true,
		);

		$result = \Deploy_Forge_Data_Formatter::format_branch_for_select( $branch );

		$this->assertSame( 'develop', $result['name'] );
		$this->assertSame( 'develop', $result['label'] );
		$this->assertTrue( $result['protected'] );
	}

	/**
	 * Test format_deployment_for_json.
	 *
	 * @return void
	 */
	public function test_format_deployment_for_json(): void {
		$deployment = (object) array(
			'id'              => 42,
			'commit_hash'     => 'abc123def456789',
			'commit_message'  => 'Fix bug in login',
			'commit_author'   => 'John Doe',
			'status'          => 'success',
			'trigger_type'    => 'webhook',
			'created_at'      => '2025-01-15 10:30:00',
			'deployed_at'     => '2025-01-15 10:35:00',
			'deployment_logs' => 'Deployment completed',
			'build_url'       => 'https://github.com/owner/repo/actions/runs/123',
			'error_message'   => '',
			'backup_path'     => '/backups/theme-backup.zip',
		);

		$result = \Deploy_Forge_Data_Formatter::format_deployment_for_json( $deployment );

		$this->assertSame( 42, $result['id'] );
		$this->assertSame( 'abc123def456789', $result['commit_hash'] );
		$this->assertSame( 'Fix bug in login', $result['commit_message'] );
		$this->assertSame( 'John Doe', $result['commit_author'] );
		$this->assertSame( 'success', $result['status'] );
		$this->assertSame( 'webhook', $result['trigger_type'] );
		$this->assertSame( '2025-01-15 10:30:00', $result['created_at'] );
		$this->assertSame( '2025-01-15 10:35:00', $result['deployed_at'] );
	}

	/**
	 * Test format_deployment_for_json uses created_at when deployed_at is null.
	 *
	 * @return void
	 */
	public function test_format_deployment_for_json_uses_created_at_fallback(): void {
		$deployment = (object) array(
			'id'             => 42,
			'commit_hash'    => 'abc123',
			'commit_message' => 'Test',
			'commit_author'  => 'Test Author',
			'status'         => 'pending',
			'trigger_type'   => 'manual',
			'created_at'     => '2025-01-15 10:30:00',
			'deployed_at'    => null,
		);

		$result = \Deploy_Forge_Data_Formatter::format_deployment_for_json( $deployment );

		$this->assertSame( '2025-01-15 10:30:00', $result['deployed_at'] );
	}

	/**
	 * Test sanitize_repository_data.
	 *
	 * @return void
	 */
	public function test_sanitize_repository_data(): void {
		$data = array(
			'owner'     => '  owner-name  ',
			'name'      => 'repo-name',
			'branch'    => 'feature/test',
			'full_name' => 'owner-name/repo-name',
		);

		$result = \Deploy_Forge_Data_Formatter::sanitize_repository_data( $data );

		$this->assertSame( 'owner-name', $result['owner'] );
		$this->assertSame( 'repo-name', $result['name'] );
		$this->assertSame( 'feature/test', $result['branch'] );
		$this->assertSame( 'owner-name/repo-name', $result['full_name'] );
	}

	/**
	 * Test sanitize_repository_data with missing fields.
	 *
	 * @return void
	 */
	public function test_sanitize_repository_data_with_missing_fields(): void {
		$result = \Deploy_Forge_Data_Formatter::sanitize_repository_data( array() );

		$this->assertSame( '', $result['owner'] );
		$this->assertSame( '', $result['name'] );
		$this->assertSame( 'main', $result['branch'] ); // Default value.
		$this->assertSame( '', $result['full_name'] );
	}

	/**
	 * Test format_repositories formats multiple repos.
	 *
	 * @return void
	 */
	public function test_format_repositories(): void {
		$repos = array(
			array(
				'full_name' => 'owner/repo1',
				'name'      => 'repo1',
				'owner'     => array( 'login' => 'owner' ),
			),
			array(
				'full_name' => 'owner/repo2',
				'name'      => 'repo2',
				'owner'     => array( 'login' => 'owner' ),
			),
		);

		$result = \Deploy_Forge_Data_Formatter::format_repositories( $repos );

		$this->assertCount( 2, $result );
		$this->assertSame( 'owner/repo1', $result[0]['full_name'] );
		$this->assertSame( 'owner/repo2', $result[1]['full_name'] );
	}

	/**
	 * Test format_branches formats multiple branches.
	 *
	 * @return void
	 */
	public function test_format_branches(): void {
		$branches = array( 'main', 'develop', 'feature/test' );

		$result = \Deploy_Forge_Data_Formatter::format_branches( $branches );

		$this->assertCount( 3, $result );
		$this->assertSame( 'main', $result[0]['name'] );
		$this->assertSame( 'develop', $result[1]['name'] );
		$this->assertSame( 'feature/test', $result[2]['name'] );
	}
}
