<?php

namespace Dorgflow\Command;

class CreatePatch extends CommandBase {

  public function execute() {
    // TEMPORARY: get services from the container.
    // @todo inject these.
    $this->git_info = $this->container->get('git.info');
    $this->analyser = $this->container->get('analyser');
    $this->waypoint_manager_branches = $this->container->get('waypoint_manager.branches');
    $this->waypoint_manager_patches = $this->container->get('waypoint_manager.patches');
    $this->drupal_org = $this->container->get('drupal_org');

    // Check git is clean.
    $clean = $this->git_info->gitIsClean();
    if (!$clean) {
      throw new \Exception("Git repository is not clean. Aborting.");
    }

    // Create branches.
    $master_branch = $this->waypoint_manager_branches->getMasterBranch();
    $feature_branch = $this->waypoint_manager_branches->getFeatureBranch();

    // If the feature branch doesn't exist or is not current, abort.
    if (!$feature_branch->exists()) {
      throw new \Exception("Feature branch does not exist.");
    }
    if (!$feature_branch->isCurrentBranch()) {
      throw new \Exception("Feature branch is not the current branch.");
    }

    // TODO: get this from user input.
    $sequential = FALSE;

    // Select the diff command to use.
    if ($sequential) {
      $command = 'format-patch --stdout';
    }
    else {
      $command = 'diff';
    }

    $master_branch_name = $master_branch->getBranchName();
    $patch_name = $this->getPatchName($feature_branch);

    shell_exec("git $command $master_branch_name > $patch_name");

    print("Written patch $patch_name with diff from $master_branch_name to local branch.\n");

    // Make an interdiff from the most recent patch.
    // (Before we make a recording patch, of course!)
    $last_patch = $this->waypoint_manager_patches->getMostRecentPatch();
    if (!empty($last_patch)) {
      $interdiff_name = $this->getInterdiffName($feature_branch, $last_patch);
      $last_patch_sha = $last_patch->getSHA();

      shell_exec("git diff $last_patch_sha > $interdiff_name");

      print("Written interdiff $interdiff_name with diff from $last_patch_sha to local branch.\n");
    }

    // Make an empty commit to record the patch.
    // TODO: find nice place for this.
    shell_exec("git commit --allow-empty -m 'Patch for Drupal.org. File: $patch_name. Automatic commit by dorgflow.'");
  }

  protected function getPatchName($feature_branch) {
    $issue_number = $this->analyser->deduceIssueNumber();
    $comment_number = $this->drupal_org->getNextCommentIndex();
    $patch_number = "$issue_number-$comment_number";
    $current_project = $this->analyser->getCurrentProjectName();
    $branch_description = $feature_branch->getBranchDescription();

    return "$patch_number.$current_project.$branch_description.patch";
  }

  protected function getInterdiffName($feature_branch, $last_patch) {
    // TODO: include the comment number of the previous patch, once we have
    // these.
    $issue_number = $this->analyser->deduceIssueNumber();
    $comment_number = $this->drupal_org->getNextCommentIndex();

    return "interdiff.$issue_number.$comment_number.txt";
  }

}
