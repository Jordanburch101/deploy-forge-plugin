/**
 * GitHub Auto-Deploy Admin JavaScript
 */

(function ($) {
  "use strict";

  const GitHubDeployAdmin = {
    init: function () {
      this.bindEvents();
      this.autoRefresh();
    },

    bindEvents: function () {
      // GitHub App connection
      $("#connect-github-btn").on("click", this.connectGitHub.bind(this));
      $("#disconnect-github-btn").on("click", this.disconnectGitHub.bind(this));

      // Repository binding
      $("#repo-select").on("change", this.onRepoSelectChange.bind(this));
      $("#bind-repo-btn").on("click", this.bindRepository.bind(this));

      // Test connection
      $("#test-connection-btn").on("click", this.testConnection.bind(this));

      // Deploy now
      $("#deploy-now-btn").on("click", this.deployNow.bind(this));

      // Refresh status
      $("#refresh-status-btn").on("click", this.refreshStatus.bind(this));

      // Rollback
      $(".rollback-btn").on("click", this.rollback.bind(this));

      // Cancel deployment
      $(".cancel-deployment-btn").on("click", this.cancelDeployment.bind(this));

      // View details
      $(".view-details-btn").on("click", this.viewDetails.bind(this));

      // Close modal
      $(".github-deploy-modal-close").on("click", this.closeModal.bind(this));
      $(".github-deploy-modal").on("click", function (e) {
        if (e.target === this) {
          $(this).hide();
        }
      });

      // Load installation repos if repo selector is present
      if ($("#repo-selector-section").length) {
        console.log('Repo selector found, loading installation repos...');
        this.loadInstallationRepos();
      } else {
        console.log('Repo selector section not found on page');
      }
    },

    connectGitHub: function (e) {
      e.preventDefault();

      const button = $(e.target).closest("button");
      const spinner = $("#connect-loading");

      button.prop("disabled", true);
      spinner.addClass("is-active");

      $.ajax({
        url: githubDeployAdmin.ajaxUrl,
        method: "POST",
        data: {
          action: "github_deploy_get_connect_url",
          nonce: githubDeployAdmin.nonce,
        },
        success: function (response) {
          if (response.success && response.data.connect_url) {
            // Redirect to OAuth flow
            window.location.href = response.data.connect_url;
          } else {
            alert(response.data?.message || "Failed to get connection URL");
            button.prop("disabled", false);
            spinner.removeClass("is-active");
          }
        },
        error: function () {
          alert("An error occurred. Please try again.");
          button.prop("disabled", false);
          spinner.removeClass("is-active");
        },
      });
    },

    disconnectGitHub: function (e) {
      e.preventDefault();

      if (!confirm("Are you sure you want to disconnect from GitHub? You will need to reconnect to continue using automatic deployments.")) {
        return;
      }

      const button = $(e.target).closest("button");
      const spinner = $("#disconnect-loading");

      button.prop("disabled", true);
      spinner.addClass("is-active");

      $.ajax({
        url: githubDeployAdmin.ajaxUrl,
        method: "POST",
        data: {
          action: "github_deploy_disconnect",
          nonce: githubDeployAdmin.nonce,
        },
        success: function (response) {
          if (response.success) {
            // Reload the page to show disconnected state
            window.location.reload();
          } else {
            alert(response.data?.message || "Failed to disconnect");
            button.prop("disabled", false);
            spinner.removeClass("is-active");
          }
        },
        error: function () {
          alert("An error occurred. Please try again.");
          button.prop("disabled", false);
          spinner.removeClass("is-active");
        },
      });
    },

    testConnection: function (e) {
      e.preventDefault();

      const button = $(e.target);
      const originalText = button.text();
      const resultDiv = $("#connection-result");

      button.prop("disabled", true).text(githubDeployAdmin.strings.testing);

      $.ajax({
        url: githubDeployAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "github_deploy_test_connection",
          nonce: githubDeployAdmin.nonce,
        },
        success: function (response) {
          if (response.success) {
            resultDiv.html(
              '<div class="notice notice-success"><p>' +
                response.message +
                "</p></div>"
            );
          } else {
            resultDiv.html(
              '<div class="notice notice-error"><p>' +
                response.message +
                "</p></div>"
            );
          }
        },
        error: function () {
          resultDiv.html(
            '<div class="notice notice-error"><p>Connection test failed.</p></div>'
          );
        },
        complete: function () {
          button.prop("disabled", false).text(originalText);
        },
      });
    },

    deployNow: function (e) {
      e.preventDefault();

      if (!confirm(githubDeployAdmin.strings.confirmDeploy)) {
        return;
      }

      const button = $(e.target);
      const originalHtml = button.html();

      button
        .prop("disabled", true)
        .html(
          '<span class="github-deploy-loading"></span> ' +
            githubDeployAdmin.strings.deploying
        );

      $.ajax({
        url: githubDeployAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "github_deploy_manual_deploy",
          nonce: githubDeployAdmin.nonce,
        },
        success: function (response) {
          if (response.success) {
            alert(response.data.message);
            location.reload();
          } else {
            // Check if error is due to deployment in progress
            if (
              response.data.error_code === "deployment_in_progress" &&
              response.data.building_deployment
            ) {
              const buildingDep = response.data.building_deployment;
              const message =
                response.data.message +
                "\n\n" +
                "Deployment #" +
                buildingDep.id +
                " (Status: " +
                buildingDep.status +
                ")\n" +
                "Commit: " +
                buildingDep.commit_hash.substring(0, 7) +
                "\n\n" +
                "Would you like to cancel it now?";

              if (confirm(message)) {
                // Cancel the existing deployment
                GitHubDeployAdmin.cancelExistingDeployment(
                  buildingDep.id,
                  button,
                  originalHtml
                );
              } else {
                button.prop("disabled", false).html(originalHtml);
              }
            } else {
              alert(response.data.message || "Deployment failed.");
              button.prop("disabled", false).html(originalHtml);
            }
          }
        },
        error: function () {
          alert("Deployment request failed.");
          button.prop("disabled", false).html(originalHtml);
        },
      });
    },

    cancelExistingDeployment: function (deploymentId, button, originalHtml) {
      $.ajax({
        url: githubDeployAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "github_deploy_cancel",
          nonce: githubDeployAdmin.nonce,
          deployment_id: deploymentId,
        },
        success: function (response) {
          if (response.success) {
            alert(
              "Previous deployment cancelled. Please click 'Deploy Now' again to start a new deployment."
            );
            location.reload();
          } else {
            alert(
              "Failed to cancel existing deployment: " +
                (response.data.message || "Unknown error")
            );
            button.prop("disabled", false).html(originalHtml);
          }
        },
        error: function () {
          alert("Failed to cancel existing deployment.");
          button.prop("disabled", false).html(originalHtml);
        },
      });
    },

    refreshStatus: function (e) {
      e.preventDefault();

      const button = $(e.target);
      const originalHtml = button.html();

      button
        .prop("disabled", true)
        .html('<span class="github-deploy-loading"></span>');

      $.ajax({
        url: githubDeployAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "github_deploy_get_status",
          nonce: githubDeployAdmin.nonce,
        },
        success: function (response) {
          if (response.success) {
            location.reload();
          }
        },
        complete: function () {
          button.prop("disabled", false).html(originalHtml);
        },
      });
    },

    rollback: function (e) {
      e.preventDefault();

      if (!confirm(githubDeployAdmin.strings.confirmRollback)) {
        return;
      }

      const button = $(e.target);
      const deploymentId = button.data("deployment-id");
      const originalText = button.text();

      button.prop("disabled", true).text("Rolling back...");

      $.ajax({
        url: githubDeployAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "github_deploy_rollback",
          nonce: githubDeployAdmin.nonce,
          deployment_id: deploymentId,
        },
        success: function (response) {
          if (response.success) {
            alert(response.data.message);
            location.reload();
          } else {
            alert(response.data.message || "Rollback failed.");
            button.prop("disabled", false).text(originalText);
          }
        },
        error: function () {
          alert("Rollback request failed.");
          button.prop("disabled", false).text(originalText);
        },
      });
    },

    cancelDeployment: function (e) {
      e.preventDefault();

      if (!confirm(githubDeployAdmin.strings.confirmCancel)) {
        return;
      }

      const button = $(e.target).closest("button");
      const deploymentId = button.data("deployment-id");
      const originalHtml = button.html();

      button
        .prop("disabled", true)
        .html(
          '<span class="github-deploy-loading"></span> ' +
            githubDeployAdmin.strings.cancelling
        );

      $.ajax({
        url: githubDeployAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "github_deploy_cancel",
          nonce: githubDeployAdmin.nonce,
          deployment_id: deploymentId,
        },
        success: function (response) {
          if (response.success) {
            alert(response.data.message);
            location.reload();
          } else {
            alert(response.data.message || "Cancellation failed.");
            button.prop("disabled", false).html(originalHtml);
          }
        },
        error: function () {
          alert("Cancellation request failed.");
          button.prop("disabled", false).html(originalHtml);
        },
      });
    },

    viewDetails: function (e) {
      e.preventDefault();

      const button = $(e.target);
      const deploymentId = button.data("deployment-id");
      const modal = $("#deployment-details-modal");
      const content = $("#deployment-details-content");

      modal.show();
      content.html("<p>Loading...</p>");

      $.ajax({
        url: githubDeployAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "github_deploy_get_status",
          nonce: githubDeployAdmin.nonce,
          deployment_id: deploymentId,
        },
        success: function (response) {
          if (response.success && response.data.deployment) {
            const d = response.data.deployment;
            let html = '<table class="widefat">';
            html +=
              "<tr><th>Commit Hash</th><td><code>" +
              d.commit_hash +
              "</code></td></tr>";
            html +=
              "<tr><th>Message</th><td>" +
              (d.commit_message || "N/A") +
              "</td></tr>";
            html +=
              "<tr><th>Author</th><td>" +
              (d.commit_author || "N/A") +
              "</td></tr>";
            html +=
              '<tr><th>Status</th><td><span class="deployment-status status-' +
              d.status +
              '">' +
              d.status +
              "</span></td></tr>";
            html +=
              "<tr><th>Trigger Type</th><td>" + d.trigger_type + "</td></tr>";
            html += "<tr><th>Created At</th><td>" + d.created_at + "</td></tr>";

            if (d.deployed_at) {
              html +=
                "<tr><th>Deployed At</th><td>" + d.deployed_at + "</td></tr>";
            }

            if (d.build_url) {
              html +=
                '<tr><th>Build URL</th><td><a href="' +
                d.build_url +
                '" target="_blank">' +
                d.build_url +
                "</a></td></tr>";
            }

            html += "</table>";

            if (d.deployment_logs) {
              html += "<h3>Deployment Logs</h3>";
              html += "<pre>" + d.deployment_logs + "</pre>";
            }

            if (d.error_message) {
              html += "<h3>Error Message</h3>";
              html += "<pre>" + d.error_message + "</pre>";
            }

            content.html(html);
          } else {
            content.html("<p>Failed to load deployment details.</p>");
          }
        },
        error: function () {
          content.html("<p>Error loading deployment details.</p>");
        },
      });
    },

    closeModal: function () {
      $(".github-deploy-modal").hide();
    },

    loadInstallationRepos: function () {
      const $loading = $("#repo-selector-loading");
      const $list = $("#repo-selector-list");
      const $error = $("#repo-selector-error");
      const $errorMessage = $("#repo-selector-error-message");
      const $select = $("#repo-select");

      $loading.show();
      $list.hide();
      $error.hide();

      $.ajax({
        url: githubDeployAdmin.ajaxUrl,
        method: "POST",
        data: {
          action: "github_deploy_get_installation_repos",
          nonce: githubDeployAdmin.nonce,
        },
        success: function (response) {
          console.log('Installation repos response:', response);
          $loading.hide();

          if (response.success && response.data.repos) {
            // Populate dropdown
            $select.html('<option value="">-- Select a Repository --</option>');

            response.data.repos.forEach(function (repo) {
              const icon = repo.private ? "🔒 " : "📖 ";
              $select.append(
                $("<option></option>")
                  .val(JSON.stringify({
                    owner: repo.owner,
                    name: repo.name,
                    full_name: repo.full_name,
                    default_branch: repo.default_branch,
                  }))
                  .text(icon + repo.full_name)
              );
            });

            $list.show();
          } else {
            console.error('Failed to load repos:', response);
            $errorMessage.text(response.data?.message || "Failed to load repositories");
            $error.show();
          }
        },
        error: function (xhr, status, error) {
          console.error('AJAX error loading repos:', xhr, status, error);
          $loading.hide();
          $errorMessage.text("An error occurred while loading repositories: " + error);
          $error.show();
        },
      });
    },

    onRepoSelectChange: function () {
      const $select = $("#repo-select");
      const $button = $("#bind-repo-btn");

      if ($select.val()) {
        $button.prop("disabled", false);
      } else {
        $button.prop("disabled", true);
      }
    },

    bindRepository: function (e) {
      e.preventDefault();

      const $select = $("#repo-select");
      const $button = $("#bind-repo-btn");
      const $spinner = $("#bind-loading");

      if (!$select.val()) {
        return;
      }

      const repoData = JSON.parse($select.val());

      if (!confirm(
        "Are you sure you want to bind to " + repoData.full_name + "?\n\n" +
        "This action is permanent and cannot be undone without disconnecting from GitHub."
      )) {
        return;
      }

      $button.prop("disabled", true);
      $select.prop("disabled", true);
      $spinner.addClass("is-active");

      $.ajax({
        url: githubDeployAdmin.ajaxUrl,
        method: "POST",
        data: {
          action: "github_deploy_bind_repo",
          nonce: githubDeployAdmin.nonce,
          owner: repoData.owner,
          name: repoData.name,
          default_branch: repoData.default_branch,
        },
        success: function (response) {
          if (response.success) {
            alert(response.data.message || "Repository bound successfully!");
            // Reload page to show bound state
            window.location.reload();
          } else {
            alert(response.data?.message || "Failed to bind repository");
            $button.prop("disabled", false);
            $select.prop("disabled", false);
            $spinner.removeClass("is-active");
          }
        },
        error: function () {
          alert("An error occurred. Please try again.");
          $button.prop("disabled", false);
          $select.prop("disabled", false);
          $spinner.removeClass("is-active");
        },
      });
    },

    autoRefresh: function () {
      // Auto-refresh status every 30 seconds if on dashboard
      if ($(".github-deploy-dashboard").length > 0) {
        setInterval(function () {
          // Check for pending deployments
          const pendingCount = $(
            ".deployment-status.status-pending, .deployment-status.status-building"
          ).length;
          if (pendingCount > 0) {
            // Silently refresh status
            $.ajax({
              url: githubDeployAdmin.ajaxUrl,
              type: "POST",
              data: {
                action: "github_deploy_get_status",
                nonce: githubDeployAdmin.nonce,
              },
              success: function (response) {
                if (response.success) {
                  // Only reload if status changed
                  const oldPending = pendingCount;
                  // This is a simplified check - in production, you'd compare actual statuses
                  location.reload();
                }
              },
            });
          }
        }, 30000); // 30 seconds
      }
    },
  };

  // Initialize when document is ready
  $(document).ready(function () {
    GitHubDeployAdmin.init();
  });
})(jQuery);

/**
 * GitHub Repository Selector
 */
(function ($) {
  "use strict";

  const GitHubRepoSelector = {
    init: function () {
      if ($(".github-deploy-settings").length === 0) {
        return; // Only run on settings page
      }

      this.bindEvents();
    },

    bindEvents: function () {
      $("#load-repos-btn").on("click", this.loadRepositories.bind(this));
      $("#repo-selector").on("change", this.onRepoSelect.bind(this));
      $("#workflow-selector").on("change", this.onWorkflowSelect.bind(this));
    },

    loadRepositories: function (e) {
      if (e) e.preventDefault();

      const $button = $("#load-repos-btn");
      const $select = $("#repo-selector");
      const $spinner = $("#repo-loading");

      // Show loading state
      $button.prop("disabled", true);
      $spinner.addClass("is-active");
      $select
        .html('<option value="">Loading repositories...</option>')
        .prop("disabled", true);

      $.ajax({
        url: githubDeployAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "github_deploy_get_repos",
          nonce: githubDeployAdmin.nonce,
        },
        success: function (response) {
          if (response.success && response.data.repos) {
            $select.empty();
            $select.append(
              '<option value="">-- Select a repository --</option>'
            );

            response.data.repos.forEach(function (repo) {
              const icon = repo.private ? "🔒 " : "📖 ";
              const workflowBadge = repo.has_workflows ? " ⚙️" : "";

              $select.append(
                $("<option></option>")
                  .val(
                    JSON.stringify({
                      owner: repo.owner,
                      name: repo.name,
                      branch: repo.default_branch,
                    })
                  )
                  .text(icon + repo.full_name + workflowBadge)
              );
            });

            $select.prop("disabled", false);

            // Show success message
            $(
              '<div class="notice notice-success is-dismissible"><p>Loaded ' +
                response.data.repos.length +
                " repositories!</p></div>"
            )
              .insertAfter("h1")
              .delay(3000)
              .fadeOut();
          } else {
            $select.html(
              '<option value="">Error loading repositories</option>'
            );
            alert(
              response.data?.message ||
                "Failed to load repositories. Make sure your GitHub token is valid."
            );
          }
        },
        error: function () {
          $select.html('<option value="">Error loading repositories</option>');
          alert(
            "Failed to load repositories. Please check your connection and try again."
          );
        },
        complete: function () {
          $button.prop("disabled", false);
          $spinner.removeClass("is-active");
        },
      });
    },

    onRepoSelect: function (e) {
      const value = $(e.target).val();

      if (!value) {
        $("#workflow-selector-row").hide();
        return;
      }

      try {
        const repo = JSON.parse(value);

        // Auto-fill manual entry fields
        $("#github_repo_owner").val(repo.owner);
        $("#github_repo_name").val(repo.name);
        $("#github_branch").val(repo.branch);

        // Load workflows for this repo
        this.loadWorkflows(repo.owner, repo.name);
      } catch (err) {
        console.error("Error parsing repo data:", err);
      }
    },

    loadWorkflows: function (owner, repo) {
      const $row = $("#workflow-selector-row");
      const $select = $("#workflow-selector");
      const $spinner = $("#workflow-loading");

      // Show loading state
      $row.show();
      $spinner.addClass("is-active");
      $select
        .html('<option value="">Loading workflows...</option>')
        .prop("disabled", true);

      $.ajax({
        url: githubDeployAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "github_deploy_get_workflows",
          nonce: githubDeployAdmin.nonce,
          owner: owner,
          repo: repo,
        },
        success: function (response) {
          if (response.success && response.data.workflows) {
            $select.empty();
            $select.append('<option value="">-- Select a workflow --</option>');

            if (response.data.workflows.length === 0) {
              $select.append(
                '<option value="" disabled>No workflows found</option>'
              );
            } else {
              response.data.workflows.forEach(function (workflow) {
                const stateIcon = workflow.state === "active" ? "✓ " : "⚠️ ";

                $select.append(
                  $("<option></option>")
                    .val(workflow.filename)
                    .text(
                      stateIcon + workflow.name + " (" + workflow.filename + ")"
                    )
                );
              });
            }

            $select.prop("disabled", false);
          } else {
            $select.html('<option value="">No workflows found</option>');
          }
        },
        error: function () {
          $select.html('<option value="">Error loading workflows</option>');
        },
        complete: function () {
          $spinner.removeClass("is-active");
        },
      });
    },

    onWorkflowSelect: function (e) {
      const value = $(e.target).val();

      if (value) {
        $("#github_workflow_name").val(value);
      }
    },
  };

  // Initialize on document ready
  $(document).ready(function () {
    GitHubRepoSelector.init();
  });
})(jQuery);

/**
 * Generate Webhook Secret Handler
 */
(function ($) {
  "use strict";

  const SecretGenerator = {
    init: function () {
      $("#generate-secret-btn").on("click", this.generateSecret.bind(this));
    },

    generateSecret: function (e) {
      e.preventDefault();

      const $button = $("#generate-secret-btn");
      const $spinner = $("#secret-loading");

      $button.prop("disabled", true);
      $spinner.addClass("is-active");

      $.ajax({
        url: githubDeployAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "github_deploy_generate_secret",
          nonce: githubDeployAdmin.nonce,
        },
        success: function (response) {
          if (response.success) {
            $("#webhook_secret").val(response.data.secret);

            // Show success message
            const $notice = $(
              '<div class="notice notice-success is-dismissible" style="margin: 15px 0;">' +
                "<p>Webhook secret generated successfully!</p>" +
                "</div>"
            );
            $(".github-deploy-settings h1").after($notice);

            setTimeout(function () {
              $notice.fadeOut(function () {
                $(this).remove();
              });
            }, 3000);
          } else {
            alert(
              "Failed to generate secret: " +
                (response.data.message || "Unknown error")
            );
          }
        },
        error: function () {
          alert("Failed to generate secret. Please try again.");
        },
        complete: function () {
          $button.prop("disabled", false);
          $spinner.removeClass("is-active");
        },
      });
    },
  };

  $(document).ready(function () {
    SecretGenerator.init();
  });
})(jQuery);

/**
 * Nonce Refresh Handler
 * Prevents "link expired" errors on settings page
 */
(function ($) {
  "use strict";

  const NonceRefresh = {
    // WordPress nonces expire after 12-24 hours (depends on settings)
    // We'll warn after 1 hour and auto-refresh form after that
    WARNING_TIME: 60 * 60 * 1000, // 1 hour in milliseconds

    init: function () {
      if ($(".github-deploy-settings").length === 0) {
        return; // Only run on settings page
      }

      this.pageLoadTime = Date.now();
      this.checkExpiration();
    },

    checkExpiration: function () {
      const self = this;

      // Check every 5 minutes
      setInterval(function () {
        const timeElapsed = Date.now() - self.pageLoadTime;

        // After 1 hour, show warning
        if (timeElapsed > self.WARNING_TIME) {
          self.showWarning();
        }
      }, 5 * 60 * 1000); // Check every 5 minutes
    },

    showWarning: function () {
      // Only show once
      if (this.warningShown) {
        return;
      }
      this.warningShown = true;

      const $notice = $(
        '<div class="notice notice-warning is-dismissible" style="margin: 15px 0;">' +
          "<p><strong>Notice:</strong> This page has been open for a while. " +
          'Please <a href="#" id="refresh-settings-page">refresh the page</a> before saving to avoid security errors.</p>' +
          "</div>"
      );

      $(".github-deploy-settings h1").after($notice);

      // Handle refresh click
      $("#refresh-settings-page").on("click", function (e) {
        e.preventDefault();
        location.reload();
      });

      // Auto-dismiss after 10 seconds
      setTimeout(function () {
        $notice.fadeOut(function () {
          $(this).remove();
        });
      }, 10000);
    },
  };

  // Initialize on document ready
  $(document).ready(function () {
    NonceRefresh.init();
  });
})(jQuery);
