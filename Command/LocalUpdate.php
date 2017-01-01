<?php

namespace Dorgflow\Command;

use Dorgflow\Situation;

class LocalUpdate extends CommandBase {

  public function execute() {
    $situation = $this->situation;

    // Check git is clean.
    $clean = $situation->GitStatus()->gitIsClean();
    if (!$clean) {
      throw new \Exception("Git repository is not clean. Aborting.");
    }

    // Create branches.
    $master_branch = $situation->getMasterBranch();
    $feature_branch = $situation->getFeatureBranch();

    // If the feature branch is not current, abort.
    if (!$feature_branch->exists()) {
      print "Could not find a feature branch. Aborting.";
      exit();
    }
    if (!$feature_branch->isCurrentBranch()) {
      print strtr("Detected feature branch !branch, but it is not the current branch. Aborting.", [
        '!branch' => $feature_branch->getBranchName(),
      ]);
      exit();
    }

    // Get the patches and create them.
    $patches = $situation->setUpPatches();
    //dump($patches);

    // If no patches, we're done.
    if (empty($patches)) {
      print "No patches to apply.\n";
      return;
    }

    $patches_uncommitted = [];
    $last_committed_patch = NULL;

    // Find the first new, uncommitted patch.
    foreach ($patches as $patch) {
      if ($patch->hasCommit()) {
        // Keep updating this, so the last time it's set gives us the last
        // committed patch.
        $last_committed_patch = $patch;
      }
      else {
        $patches_uncommitted[] = $patch;
      }
    }

    // If no uncommitted patches, we're done.
    if (empty($patches_uncommitted)) {
      print "No patches to apply; existing patches are already applied to this feature branch.\n";
      return;
    }

    // If the feature branch's SHA is not the same as the last committed patch
    // SHA, then that means there are local commits on the branch that are
    // newer than the patch.
    if ($last_committed_patch->getSHA() != $feature_branch->getSHA()) {
      // Create a new branch at the tip of the feature branch.
      $forked_branch_name = $feature_branch->createForkBranchName();
      $this->git->createNewBranch($forked_branch_name);

      // Reposition the FeatureBranch tip to the last committed patch.
      $this->git->moveBranch($feature_branch->getBranchName(), $last_committed_patch->getSHA());

      print strtr("Moved your work at the tip of the feature branch to new branch !forkedbranchname. You should manually merge this into the feature branch to preserve your work.\n", [
        '!forkedbranchname' => $patch->getPatchFilename(),
      ]);

      // We're now ready to apply the patches.
    }

    // Output the patches.
    $patches_committed = [];
    foreach ($patches_uncommitted as $patch) {
      // TODO: handle case where there are local commits at the tip of the branch
      // rather than a patch!

      // Commit the patch.
      $patch_committed = $patch->commitPatch();

      // Message.
      if ($patch_committed) {
        // Keep a list of the patches that we commit.
        $patches_committed[] = $patch;

        print strtr("Applied patch !patchname.\n", [
          '!patchname' => $patch->getPatchFilename(),
        ]);
      }
      else {
        print strtr("Patch !patchname did not apply.\n", [
          '!patchname' => $patch->getPatchFilename(),
        ]);
      }
    }

    // If all the patches were already committed, we're done.
    if (empty($patches_committed)) {
      print "No new patches to apply.\n";
      return;
    }

    // If final patch didn't apply, then output a message: the latest patch
    // has rotted. Save the patch file to disk and give the filename in the
    // message.
    if (!$patch_committed) {
      // Save the file so the user can apply it manually.
      file_put_contents($patch->getPatchFilename(), $patch->getPatchFile());

      print strtr("The most recent patch, !patchname, did not apply. You should attempt to apply it manually. "
        . "The patch file has been saved to the working directory.\n", [
        '!patchname' => $patch->getPatchFilename(),
      ]);
    }
  }

}
