/**
 * Test data fixtures for E2E tests
 * Contains mock data for GitHub repositories, branches, workflows, etc.
 */

module.exports = {
  /**
   * Mock GitHub repositories
   */
  repositories: [
    {
      id: 123456,
      name: 'test-theme-repo',
      full_name: 'testuser/test-theme-repo',
      owner: {
        login: 'testuser',
        id: 12345,
        avatar_url: 'https://avatars.githubusercontent.com/u/12345',
      },
      description: 'A test WordPress theme repository',
      private: false,
      default_branch: 'main',
      html_url: 'https://github.com/testuser/test-theme-repo',
    },
    {
      id: 789012,
      name: 'another-theme',
      full_name: 'testuser/another-theme',
      owner: {
        login: 'testuser',
        id: 12345,
        avatar_url: 'https://avatars.githubusercontent.com/u/12345',
      },
      description: 'Another test theme',
      private: true,
      default_branch: 'master',
      html_url: 'https://github.com/testuser/another-theme',
    },
  ],

  /**
   * Mock GitHub branches
   */
  branches: [
    {
      name: 'main',
      commit: {
        sha: 'abc123def456',
        url: 'https://api.github.com/repos/testuser/test-theme-repo/commits/abc123',
      },
      protected: true,
    },
    {
      name: 'develop',
      commit: {
        sha: 'def456ghi789',
        url: 'https://api.github.com/repos/testuser/test-theme-repo/commits/def456',
      },
      protected: false,
    },
    {
      name: 'feature/new-design',
      commit: {
        sha: 'ghi789jkl012',
        url: 'https://api.github.com/repos/testuser/test-theme-repo/commits/ghi789',
      },
      protected: false,
    },
  ],

  /**
   * Mock GitHub Actions workflows
   */
  workflows: [
    {
      id: 12345678,
      name: 'Build Theme',
      path: '.github/workflows/build-theme.yml',
      state: 'active',
      created_at: '2024-01-01T00:00:00Z',
      updated_at: '2024-01-15T00:00:00Z',
      url: 'https://api.github.com/repos/testuser/test-theme-repo/actions/workflows/12345678',
      html_url: 'https://github.com/testuser/test-theme-repo/actions/workflows/build-theme.yml',
      badge_url: 'https://github.com/testuser/test-theme-repo/workflows/Build%20Theme/badge.svg',
    },
    {
      id: 87654321,
      name: 'Deploy to Production',
      path: '.github/workflows/deploy.yml',
      state: 'active',
      created_at: '2024-01-05T00:00:00Z',
      updated_at: '2024-01-20T00:00:00Z',
      url: 'https://api.github.com/repos/testuser/test-theme-repo/actions/workflows/87654321',
      html_url: 'https://github.com/testuser/test-theme-repo/actions/workflows/deploy.yml',
      badge_url: 'https://github.com/testuser/test-theme-repo/workflows/Deploy/badge.svg',
    },
  ],

  /**
   * Mock workflow run
   */
  workflowRun: {
    id: 9876543210,
    name: 'Build Theme',
    status: 'completed',
    conclusion: 'success',
    workflow_id: 12345678,
    html_url: 'https://github.com/testuser/test-theme-repo/actions/runs/9876543210',
    created_at: '2024-01-25T10:00:00Z',
    updated_at: '2024-01-25T10:05:00Z',
    head_branch: 'main',
    head_sha: 'abc123def456',
  },

  /**
   * Mock deployment data
   */
  deployment: {
    id: 1,
    commit_hash: 'abc123def456',
    commit_message: 'feat: add new homepage design',
    commit_author: 'Test User',
    deployed_at: '2024-01-25T10:05:30Z',
    status: 'success',
    build_url: 'https://github.com/testuser/test-theme-repo/actions/runs/9876543210',
    trigger_type: 'manual',
    triggered_by_user_id: 1,
  },

  /**
   * Mock GitHub installation data
   */
  installation: {
    id: 98765432,
    account: {
      login: 'testuser',
      id: 12345,
      avatar_url: 'https://avatars.githubusercontent.com/u/12345',
      type: 'User',
    },
    repository_selection: 'selected',
    html_url: 'https://github.com/settings/installations/98765432',
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-25T00:00:00Z',
  },

  /**
   * Mock commits
   */
  commits: [
    {
      sha: 'abc123def456',
      commit: {
        message: 'feat: add new homepage design',
        author: {
          name: 'Test User',
          email: 'test@example.com',
          date: '2024-01-25T09:00:00Z',
        },
      },
      html_url: 'https://github.com/testuser/test-theme-repo/commit/abc123def456',
    },
    {
      sha: 'def456ghi789',
      commit: {
        message: 'fix: resolve mobile layout issue',
        author: {
          name: 'Test User',
          email: 'test@example.com',
          date: '2024-01-24T15:30:00Z',
        },
      },
      html_url: 'https://github.com/testuser/test-theme-repo/commit/def456ghi789',
    },
  ],

  /**
   * Plugin settings for testing
   */
  pluginSettings: {
    repository: 'testuser/test-theme-repo',
    repository_owner: 'testuser',
    repository_name: 'test-theme-repo',
    branch: 'main',
    workflow_file: 'build-theme.yml',
    deployment_method: 'github_actions',
    auto_deploy_enabled: true,
    manual_approval_required: false,
    create_backups: true,
    webhook_enabled: true,
    webhook_secret: 'test-webhook-secret-abc123',
    installation_id: '98765432',
  },
};
