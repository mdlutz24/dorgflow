<?php

namespace Dorgflow\Tests;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Base class for command tests.
 */
abstract class CommandTestBase extends \PHPUnit\Framework\TestCase {

  /**
   * Creates a mock git.info service that will state that git is clean.
   *
   * @return
   *  The mocked git.info service object.
   */
  protected function getMockGitInfoClean() {
    $git_info = $this->getMockBuilder(\Dorgflow\Service\GitInfo::class)
      ->disableOriginalConstructor()
      ->setMethods(['gitIsClean'])
      ->getMock();
    $git_info->method('gitIsClean')
      ->willReturn(TRUE);

    return $git_info;
  }

  /**
   * Sets up the mock drupal_org service with the given patch file data.
   *
   * @param $drupal_org
   *  The mock drupal_org service.
   * @param $patch_file_data
   *  An array of data for the patch files. The key is the filefield delta; each
   *  item is an array with the following properties:
   *    - 'fid': The file entity ID.
   *    - 'cid': The comment entity ID for this file.
   *    - 'index': The comment index.
   *    - 'filename': The patch filename.
   *    - 'display': Boolean indicating whether the file is displayed.
   */
  protected function setUpDrupalOrgExpectations($drupal_org, $patch_file_data) {
    $getIssueFileFieldItems_return = [];
    $getFileEntity_value_map = [];
    $getPatchFile_value_map = [];

    foreach ($patch_file_data as $patch_file_data_item) {
      $file_field_item = (object) [
        'file' => (object) [
          'uri' => 'https://www.drupal.org/api-d7/file/' . $patch_file_data_item['fid'],
          'id' => $patch_file_data_item['fid'],
          'resource' => 'file',
          'cid' => $patch_file_data_item['cid'],
        ],
        'display' => $patch_file_data_item['display'],
        'index' => $patch_file_data_item['index'],
      ];
      $getIssueFileFieldItems_return[] = $file_field_item;

      $getFileEntity_value_map[] = [
        $patch_file_data_item['fid'],
        // For dummy file entities, we only need the url property.
        (object) ['url' => $patch_file_data_item['filename']]
      ];

      $getPatchFile_value_map[] = [
        $patch_file_data_item['filename'],
        // The contents of the patch file.
        'patch-file-data-' . $patch_file_data_item['fid']
      ];
    }

    $drupal_org->method('getIssueFileFieldItems')
      ->willReturn($getIssueFileFieldItems_return);
    $drupal_org->expects($this->any())
      ->method('getFileEntity')
      ->will($this->returnValueMap($getFileEntity_value_map));
    $drupal_org->expects($this->any())
      ->method('getPatchFile')
      ->will($this->returnValueMap($getPatchFile_value_map));
  }

  /**
   * Add any services to the container that are not yet registered on it.
   *
   * NOTE: currently only takes care of commit_message and the waypoint
   * managers.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *  The service container.
   */
  protected function completeServiceContainer(ContainerBuilder $container) {
    // TODO: add all the other services, but so far these are always mocked, to
    // YAGNI.

    if (!$container->has('commit_message')) {
      $container
        ->register('commit_message', \Dorgflow\Service\CommitMessageHandler::class)
        ->addArgument(new Reference('analyser'));
    }

    if (!$container->has('waypoint_manager.branches')) {
      $container
        ->register('waypoint_manager.branches', \Dorgflow\Service\WaypointManagerBranches::class)
        ->addArgument(new Reference('git.info'))
        ->addArgument(new Reference('drupal_org'))
        ->addArgument(new Reference('git.executor'))
        ->addArgument(new Reference('analyser'));
    }

    if (!$container->has('waypoint_manager.patches')) {
      $container
        ->register('waypoint_manager.patches', \Dorgflow\Service\WaypointManagerPatches::class)
        ->addArgument(new Reference('commit_message'))
        ->addArgument(new Reference('drupal_org'))
        ->addArgument(new Reference('git.log'))
        ->addArgument(new Reference('git.executor'))
        ->addArgument(new Reference('waypoint_manager.branches'));
    }
  }

}