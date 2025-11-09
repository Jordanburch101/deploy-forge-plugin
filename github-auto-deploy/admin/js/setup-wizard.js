/**
 * Setup Wizard JavaScript
 * Handles multi-step wizard navigation, AJAX, and Select2 integration
 */

(function ($) {
  "use strict";

  const GitHubDeployWizard = {
    currentStep: 1,
    totalSteps: 6,
    wizardData: {
      repo_owner: null,
      repo_name: null,
      branch: null,
    },

    /**
     * Initialize wizard
     */
    init: function () {
      this.currentStep = parseInt(githubDeployWizard.currentStep, 10) || 1;
      this.bindEvents();
      this.initStep(this.currentStep);
    },

    /**
     * Bind event handlers
     */
    bindEvents: function () {
      // Navigation buttons
      $(document).on("click", ".wizard-next", this.handleNext.bind(this));
      $(document).on("click", ".wizard-back", this.handleBack.bind(this));
      $(document).on("click", ".wizard-skip", this.handleSkip.bind(this));
      $(document).on("click", ".wizard-complete", this.handleComplete.bind(this));

      // Step-specific events
      $(document).on("click", ".wizard-connect-button", this.handleConnectClick.bind(this));
      $(document).on("change", "#repository-select", this.handleRepoChange.bind(this));
      $(document).on("change", "#branch-select", this.handleBranchChange.bind(this));
      $(document).on("click", ".wizard-option-card", this.handleMethodSelect.bind(this));
      $(document).on("change", "#workflow-select", this.handleWorkflowChange.bind(this));

      // Toggle switches
      $(document).on("change", ".wizard-toggle-switch input", this.handleToggleChange.bind(this));

      // Copy buttons
      $(document).on("click", ".wizard-copy-button", this.handleCopy.bind(this));

      // Auto-check connection status on step 2
      if (this.currentStep === 2 && githubDeployWizard.isConnected) {
        this.showConnectionSuccess();
      }
    },

    /**
     * Initialize specific step
     */
    initStep: function (stepNumber) {
      this.currentStep = parseInt(stepNumber, 10);
      this.updateProgress();
      this.updateNavigation();

      // Step-specific initialization
      switch (stepNumber) {
        case 2:
          this.initConnectStep();
          break;
        case 3:
          this.initRepositoryStep();
          break;
        case 4:
          this.initMethodStep();
          break;
        case 5:
          this.initOptionsStep();
          break;
        case 6:
          this.initReviewStep();
          break;
      }
    },

    /**
     * Update progress stepper
     */
    updateProgress: function () {
      $(".wizard-step").each((index, element) => {
        const stepNum = index + 1;
        const $step = $(element);

        $step.removeClass("active completed");

        if (stepNum < this.currentStep) {
          $step.addClass("completed");
        } else if (stepNum === this.currentStep) {
          $step.addClass("active");
        }
      });
    },

    /**
     * Update navigation buttons
     */
    updateNavigation: function () {
      const $backBtn = $(".wizard-back");
      const $nextBtn = $(".wizard-next");
      const $completeBtn = $(".wizard-complete");

      // Show/hide back button
      if (this.currentStep === 1) {
        $backBtn.hide();
      } else {
        $backBtn.show();
      }

      // Show next or complete button
      if (this.currentStep === this.totalSteps) {
        $nextBtn.hide();
        $completeBtn.show();
      } else {
        $nextBtn.show();
        $completeBtn.hide();
      }

      // Validate and enable/disable next button
      this.validateCurrentStep();
    },

    /**
     * Validate current step
     */
    validateCurrentStep: function () {
      let isValid = true;

      switch (this.currentStep) {
        case 1: // Welcome - always valid
          isValid = true;
          break;

        case 2: // Connect
          isValid = githubDeployWizard.isConnected;
          break;

        case 3: // Repository
          isValid = $("#repository-select").val() && $("#branch-select").val();
          break;

        case 4: // Method
          const method = $(".wizard-option-card.selected").data("method");
          if (method === "github_actions") {
            isValid = $("#workflow-select").val() || $(".wizard-option-card.selected").data("skip-workflow");
          } else {
            isValid = !!method;
          }
          break;

        case 5: // Options - always valid (has defaults)
          isValid = true;
          break;

        case 6: // Review - always valid
          isValid = true;
          break;
      }

      $(".wizard-next, .wizard-complete").prop("disabled", !isValid);
      return isValid;
    },

    /**
     * Handle next button click
     */
    handleNext: function (e) {
      e.preventDefault();

      console.log("Next clicked, current step:", this.currentStep);

      if (!this.validateCurrentStep()) {
        console.log("Validation failed");
        return;
      }

      console.log("Validation passed, saving step data...");

      // Save current step data
      this.saveStepData(() => {
        console.log("Step data saved, navigating to step:", this.currentStep + 1);
        // Move to next step
        this.navigateToStep(this.currentStep + 1);
      });
    },

    /**
     * Handle back button click
     */
    handleBack: function (e) {
      e.preventDefault();
      this.navigateToStep(this.currentStep - 1);
    },

    /**
     * Handle skip button click
     */
    handleSkip: function (e) {
      e.preventDefault();

      if (!confirm(githubDeployWizard.strings.skipConfirm)) {
        return;
      }

      this.showLoading();

      $.ajax({
        url: githubDeployWizard.ajaxUrl,
        type: "POST",
        data: {
          action: "github_deploy_wizard_skip",
          nonce: githubDeployWizard.nonce,
        },
        success: (response) => {
          if (response.success && response.data.redirect) {
            window.location.href = response.data.redirect;
          } else {
            alert(response.data?.message || githubDeployWizard.strings.error);
            this.hideLoading();
          }
        },
        error: () => {
          alert(githubDeployWizard.strings.error);
          this.hideLoading();
        },
      });
    },

    /**
     * Handle complete button click
     */
    handleComplete: function (e) {
      e.preventDefault();

      this.showLoading();

      $.ajax({
        url: githubDeployWizard.ajaxUrl,
        type: "POST",
        data: {
          action: "github_deploy_wizard_complete",
          nonce: githubDeployWizard.nonce,
        },
        success: (response) => {
          if (response.success && response.data.redirect) {
            window.location.href = response.data.redirect;
          } else {
            alert(response.data?.message || githubDeployWizard.strings.error);
            this.hideLoading();
          }
        },
        error: () => {
          alert(githubDeployWizard.strings.error);
          this.hideLoading();
        },
      });
    },

    /**
     * Navigate to specific step
     */
    navigateToStep: function (stepNumber) {
      stepNumber = parseInt(stepNumber, 10);
      console.log(`Navigating from step ${this.currentStep} to step ${stepNumber}`);
      console.log("Before navigation - Active steps:", $(`.wizard-step-content.active`).length);

      // Hide current step
      const $currentStep = $(`.wizard-step-content[data-step="${this.currentStep}"]`);
      console.log("Current step element found:", $currentStep.length);
      $currentStep.removeClass("active");

      // Show new step
      const $newStep = $(`.wizard-step-content[data-step="${stepNumber}"]`);
      console.log("New step element found:", $newStep.length);
      $newStep.addClass("active");

      console.log("After navigation - Active steps:", $(`.wizard-step-content.active`).length);

      // Update current step
      this.initStep(stepNumber);

      // Scroll to top
      $(".wizard-content").scrollTop(0);

      console.log("Navigation complete, now on step:", this.currentStep);
    },

    /**
     * Save current step data
     */
    saveStepData: function (callback) {
      const stepData = this.collectStepData();
      console.log("Collecting step data for step", this.currentStep, ":", stepData);

      // Steps 1 and 2 don't have data to save, just proceed
      if (!stepData || Object.keys(stepData).length === 0) {
        console.log("No data to save, proceeding immediately");
        if (callback) callback();
        return;
      }

      console.log("Saving step data via AJAX...");

      $.ajax({
        url: githubDeployWizard.ajaxUrl,
        type: "POST",
        data: {
          action: "github_deploy_wizard_save_step",
          nonce: githubDeployWizard.nonce,
          step: this.currentStep,
          data: stepData,
        },
        success: () => {
          console.log("Step data saved successfully");
          if (callback) callback();
        },
        error: (xhr, status, error) => {
          console.error("Save step error:", error, xhr.responseText);
          alert(githubDeployWizard.strings.error);
          // Don't call callback on error - stay on current step
        },
      });
    },

    /**
     * Collect current step data
     */
    collectStepData: function () {
      const data = {};

      switch (this.currentStep) {
        case 3: // Repository
          const repoFullName = $("#repository-select").val();
          if (repoFullName) {
            const [owner, name] = repoFullName.split("/");
            data.repo_owner = owner;
            data.repo_name = name;
            // Save to wizard object for later steps
            this.wizardData.repo_owner = owner;
            this.wizardData.repo_name = name;
          }
          data.branch = $("#branch-select").val();
          if (data.branch) {
            this.wizardData.branch = data.branch;
          }
          break;

        case 4: // Method
          const method = $(".wizard-option-card.selected").data("method");
          data.deployment_method = method;
          if (method === "github_actions") {
            data.workflow_name = $("#workflow-select").val();
          }
          break;

        case 5: // Options
          data.auto_deploy_enabled = $("#auto-deploy-toggle").is(":checked");
          data.require_manual_approval = $("#manual-approval-toggle").is(":checked");
          data.create_backups = $("#create-backups-toggle").is(":checked");
          break;
      }

      return data;
    },

    /**
     * Initialize Connect step
     */
    initConnectStep: function () {
      if (githubDeployWizard.isConnected) {
        this.showConnectionSuccess();
      }
    },

    /**
     * Handle connect button click
     */
    handleConnectClick: function (e) {
      // No special handling needed - OAuth callback will detect wizard in progress
    },

    /**
     * Show connection success message
     */
    showConnectionSuccess: function () {
      $(".wizard-connect-status").html(`
        <div class="wizard-success-message">
          <span class="dashicons dashicons-yes-alt"></span>
          <div>
            <strong>Successfully connected to GitHub!</strong>
            <p style="margin: 4px 0 0;">You can now proceed to the next step.</p>
          </div>
        </div>
      `);

      githubDeployWizard.isConnected = true;
      this.validateCurrentStep();

      // Auto-advance after 1 second
      setTimeout(() => {
        if (this.currentStep === 2) {
          this.handleNext({ preventDefault: () => {} });
        }
      }, 1000);
    },

    /**
     * Initialize Repository step
     */
    initRepositoryStep: function () {
      // Check if Select2 is loaded
      if (typeof $.fn.select2 === 'undefined') {
        console.error("Select2 is not loaded yet!");
        // Retry after a short delay
        setTimeout(() => this.initRepositoryStep(), 100);
        return;
      }

      // Load repositories FIRST, then initialize Select2 after options are loaded
      this.loadRepositories();
    },

    /**
     * Load repositories via AJAX
     */
    loadRepositories: function () {
      const $select = $("#repository-select");
      const $loading = $(".repo-loading");

      console.log("Loading repositories...");
      $loading.show();
      $select.prop("disabled", true);

      $.ajax({
        url: githubDeployWizard.ajaxUrl,
        type: "POST",
        data: {
          action: "github_deploy_wizard_get_repos",
          nonce: githubDeployWizard.nonce,
        },
        success: (response) => {
          console.log("Repository AJAX response:", response);

          if (response.success && response.data.repositories) {
            console.log("Found", response.data.repositories.length, "repositories");

            // Clear and populate select
            $select.empty().append('<option value="">Select a repository...</option>');

            response.data.repositories.forEach((repo) => {
              const option = new Option(repo.full_name, repo.full_name, false, false);
              $(option).data("repo", repo);
              $select.append(option);
            });

            // NOW initialize Select2 with the populated options
            if (!$select.hasClass("select2-hidden-accessible")) {
              $select.select2({
                placeholder: "Search repositories...",
                width: "100%",
                minimumInputLength: 0,
                dropdownParent: $(".github-deploy-wizard"), // Keep dropdown inside wizard modal
              });
            }

            $select.prop("disabled", false);
            $loading.hide();
          } else {
            console.error("Repository load failed:", response);
            alert(response.data?.message || "Failed to load repositories");
            $loading.hide();
          }
        },
        error: (xhr, status, error) => {
          console.error("Repository AJAX error:", status, error, xhr.responseText);
          alert(githubDeployWizard.strings.error);
          $loading.hide();
        },
      });
    },

    /**
     * Handle repository selection change
     */
    handleRepoChange: function (e) {
      const repoFullName = $(e.target).val();

      if (!repoFullName) {
        $("#branch-select").prop("disabled", true).empty();
        $(".wizard-deployment-preview").hide();
        this.validateCurrentStep();
        return;
      }

      const repoData = $(e.target).find(":selected").data("repo");

      // Show deployment preview
      const [owner, name] = repoFullName.split("/");
      $(".wizard-deployment-preview").show().find("code").text(
        `/wp-content/themes/${name}/`
      );

      // IMPORTANT: Bind repository to backend FIRST, then load branches
      // This is required because the backend won't allow API requests without a bound repo
      console.log("Repository selected, binding to backend first...");
      const defaultBranch = repoData.default_branch || 'main';

      this.bindRepository(owner, name, defaultBranch, () => {
        // After binding succeeds, load branches
        console.log("Repository bound, now loading branches...");
        this.loadBranches(repoFullName, defaultBranch);
      });
    },

    /**
     * Load branches for selected repository
     */
    loadBranches: function (repoFullName, defaultBranch) {
      const $select = $("#branch-select");
      const $loading = $(".branch-loading");

      $loading.show();
      $select.prop("disabled", true).empty();

      // Check if Select2 is loaded
      if (typeof $.fn.select2 === 'undefined') {
        console.error("Select2 is not loaded yet!");
        $loading.hide();
        return;
      }

      // Initialize Select2 if not already
      if (!$select.hasClass("select2-hidden-accessible")) {
        $select.select2({
          placeholder: "Select a branch...",
          width: "100%",
          dropdownParent: $(".github-deploy-wizard"),
        });
      }

      $.ajax({
        url: githubDeployWizard.ajaxUrl,
        type: "POST",
        data: {
          action: "github_deploy_wizard_get_branches",
          nonce: githubDeployWizard.nonce,
          repo_full_name: repoFullName,
        },
        success: (response) => {
          if (response.success && response.data.branches) {
            $select.empty();

            response.data.branches.forEach((branch) => {
              const branchName = typeof branch === "string" ? branch : branch.name;
              const option = new Option(branchName, branchName, false, branchName === defaultBranch);
              $select.append(option);
            });

            $select.prop("disabled", false).trigger("change");
            $loading.hide();
            this.validateCurrentStep();
          } else {
            alert(response.data?.message || "Failed to load branches");
            $loading.hide();
          }
        },
        error: () => {
          alert(githubDeployWizard.strings.error);
          $loading.hide();
        },
      });
    },

    /**
     * Handle branch selection change
     */
    handleBranchChange: function () {
      this.validateCurrentStep();
    },

    /**
     * Initialize Method step
     */
    initMethodStep: function () {
      console.log("Initializing Method step (Step 4)");
      console.log("Wizard data:", this.wizardData);

      // Ensure one method is selected
      if (!$(".wizard-option-card.selected").length) {
        $(".wizard-option-card[data-method='github_actions']").addClass("selected");
      }

      // If GitHub Actions is selected and workflows haven't been loaded, load them
      const selectedMethod = $(".wizard-option-card.selected").data("method");
      const workflowOptionsCount = $("#workflow-select option").length;

      console.log("Selected method:", selectedMethod);
      console.log("Workflow options count:", workflowOptionsCount);

      if (selectedMethod === "github_actions" && workflowOptionsCount <= 1) {
        console.log("Loading workflows...");
        this.loadWorkflows();
      }

      this.validateCurrentStep();
    },

    /**
     * Handle deployment method selection
     */
    handleMethodSelect: function (e) {
      const $card = $(e.currentTarget);
      const method = $card.data("method");

      // Deselect all, select clicked
      $(".wizard-option-card").removeClass("selected");
      $card.addClass("selected");

      // Load workflows if GitHub Actions selected
      if (method === "github_actions" && !$("#workflow-select option").length) {
        this.loadWorkflows();
      }

      this.validateCurrentStep();
    },

    /**
     * Load workflows for selected repository
     */
    loadWorkflows: function () {
      // Get repo from wizard data (saved from step 3)
      const owner = this.wizardData.repo_owner;
      const name = this.wizardData.repo_name;

      console.log("Loading workflows for repository:", owner, "/", name);

      if (!owner || !name) {
        console.error("No repository selected - wizard data:", this.wizardData);
        return;
      }
      const $select = $("#workflow-select");
      const $loading = $(".workflow-loading");

      console.log("Workflow select element:", $select.length);
      $loading.show();
      $select.prop("disabled", true);

      // Check if Select2 is loaded
      if (typeof $.fn.select2 === 'undefined') {
        console.error("Select2 is not loaded yet!");
        $loading.hide();
        return;
      }

      // Initialize Select2
      if (!$select.hasClass("select2-hidden-accessible")) {
        $select.select2({
          placeholder: "Select a workflow...",
          width: "100%",
          dropdownParent: $(".github-deploy-wizard"),
        });
      }

      $.ajax({
        url: githubDeployWizard.ajaxUrl,
        type: "POST",
        data: {
          action: "github_deploy_wizard_get_workflows",
          nonce: githubDeployWizard.nonce,
          repo_owner: owner,
          repo_name: name,
        },
        success: (response) => {
          console.log("Workflows AJAX response:", response);

          if (response.success && response.data.workflows) {
            console.log("Found", response.data.workflows.length, "workflows");
            $select.empty();

            if (response.data.workflows.length === 0) {
              $select.append('<option value="">No workflows found</option>');
              $(".wizard-no-workflow-message").show();
            } else {
              $select.append('<option value="">Select a workflow...</option>');

              response.data.workflows.forEach((workflow) => {
                // Use filename as value (required by GitHub API), display name as label
                const option = new Option(workflow.name, workflow.filename, false, false);
                $select.append(option);
              });

              $(".wizard-no-workflow-message").hide();
            }

            $select.prop("disabled", false);
            $loading.hide();
            this.validateCurrentStep();
          } else {
            console.error("Workflows load failed:", response);
            console.error("Error message:", response.data?.message || "Unknown error");
            $loading.hide();
            $(".wizard-no-workflow-message").show();
          }
        },
        error: () => {
          $loading.hide();
          $(".wizard-no-workflow-message").show();
        },
      });
    },

    /**
     * Handle workflow selection change
     */
    handleWorkflowChange: function () {
      this.validateCurrentStep();
    },

    /**
     * Bind repository to backend
     */
    bindRepository: function (owner, name, branch, callback) {
      console.log("Binding repository:", owner, "/", name, "branch:", branch);

      $.ajax({
        url: githubDeployWizard.ajaxUrl,
        type: "POST",
        data: {
          action: "github_deploy_wizard_bind_repo",
          nonce: githubDeployWizard.nonce,
          owner: owner,
          name: name,
          default_branch: branch,
        },
        success: (response) => {
          console.log("Repository bind response:", response);
          if (response.success) {
            console.log("Repository bound successfully");
            if (callback) callback();
          } else {
            console.error("Failed to bind repository:", response.data?.message);
            alert("Failed to bind repository: " + (response.data?.message || "Unknown error"));
          }
        },
        error: (xhr, status, error) => {
          console.error("Bind repository error:", error, xhr.responseText);
          alert("Failed to bind repository. Please try again.");
        },
      });
    },

    /**
     * Initialize Options step
     */
    initOptionsStep: function () {
      // Set default values if not set
      if ($("#auto-deploy-toggle").prop("checked") === undefined) {
        $("#auto-deploy-toggle").prop("checked", true);
      }
      if ($("#create-backups-toggle").prop("checked") === undefined) {
        $("#create-backups-toggle").prop("checked", true);
      }

      this.updateToggleGroups();
    },

    /**
     * Handle toggle switch change
     */
    handleToggleChange: function (e) {
      const $toggle = $(e.target);
      const $group = $toggle.closest(".wizard-toggle-group");

      if ($toggle.is(":checked")) {
        $group.addClass("enabled");
      } else {
        $group.removeClass("enabled");
      }

      // Generate webhook secret if webhook enabled
      if ($toggle.attr("id") === "webhook-toggle" && $toggle.is(":checked")) {
        if (!$("#webhook-secret-input").val()) {
          this.generateWebhookSecret();
        }
      }

      this.validateCurrentStep();
    },

    /**
     * Update toggle group states
     */
    updateToggleGroups: function () {
      $(".wizard-toggle-group").each(function () {
        const $group = $(this);
        const $toggle = $group.find(".wizard-toggle-switch input");

        if ($toggle.is(":checked")) {
          $group.addClass("enabled");
        } else {
          $group.removeClass("enabled");
        }
      });
    },

    /**
     * Generate webhook secret
     */
    generateWebhookSecret: function () {
      const secret = Array.from({ length: 64 }, () =>
        Math.floor(Math.random() * 16).toString(16)
      ).join("");

      $("#webhook-secret-input").val(secret);
    },

    /**
     * Handle copy button click
     */
    handleCopy: function (e) {
      const $button = $(e.currentTarget);
      const $input = $button.prev("input");

      $input.select();
      document.execCommand("copy");

      $button.addClass("copied").text("✓ Copied!");

      setTimeout(() => {
        $button.removeClass("copied").text("Copy");
      }, 2000);
    },

    /**
     * Initialize Review step
     */
    initReviewStep: function () {
      // Populate review data
      this.populateReviewData();
    },

    /**
     * Populate review step with configured data
     */
    populateReviewData: function () {
      // Repository info
      const repoFullName = $("#repository-select").val();
      const branch = $("#branch-select").val();

      if (repoFullName) {
        $("#review-repo-name").text(repoFullName);
        $("#review-branch").text(branch);
      }

      // Deployment method
      const method = $(".wizard-option-card.selected").data("method");
      const methodText = method === "github_actions" ? "GitHub Actions (Build + Deploy)" : "Direct Clone (No Build)";

      $("#review-method").text(methodText);

      if (method === "github_actions") {
        const workflow = $("#workflow-select").val();
        $("#review-workflow").text(workflow || "Not selected");
        $("#review-workflow-row").show();
      } else {
        $("#review-workflow-row").hide();
      }

      // Options
      const autoDeploy = $("#auto-deploy-toggle").is(":checked");
      const manualApproval = $("#manual-approval-toggle").is(":checked");
      const backups = $("#create-backups-toggle").is(":checked");
      const webhooks = $("#webhook-toggle").is(":checked");

      $("#review-auto-deploy").toggle(autoDeploy);
      $("#review-manual-approval").toggle(manualApproval);
      $("#review-backups").toggle(backups);
      $("#review-webhooks").toggle(webhooks);
    },

    /**
     * Show loading overlay
     */
    showLoading: function () {
      $(".wizard-footer").append(
        '<div class="wizard-loading-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.8); display: flex; align-items: center; justify-content: center;"><span class="wizard-loading"></span></div>'
      );
    },

    /**
     * Hide loading overlay
     */
    hideLoading: function () {
      $(".wizard-loading-overlay").remove();
    },
  };

  // Initialize when document ready
  $(document).ready(function () {
    console.log("Document ready, checking for wizard element...");
    console.log("Wizard elements found:", $(".github-deploy-wizard").length);

    if ($(".github-deploy-wizard").length) {
      console.log("Initializing GitHubDeployWizard...");
      GitHubDeployWizard.init();
      console.log("GitHubDeployWizard initialized, current step:", GitHubDeployWizard.currentStep);
    } else {
      console.log("No wizard element found, skipping initialization");
    }
  });
})(jQuery);
