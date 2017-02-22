<?php

namespace Dorgflow\Tests;

/**
 * Unit test for the CommitMessageHandler service.
 *
 * Run with:
 * @code
 *   vendor/bin/phpunit tests/CommitMessageHandlerTest.php
 * @endcode
 */
class CommitMessageHandlerTest extends \PHPUnit\Framework\TestCase {

  /**
   * Tests parsing of commit messages.
   *
   * @dataProvider providerCommitMessages
   */
  public function testCommitMessageParser($message, $expected_data) {
    $commit_message_handler = new \Dorgflow\Service\CommitMessageHandler();

    $commit_data = $commit_message_handler->parseCommitMessage($message);

    // For ease of debugging failing tests, check each array item individually.
    if (is_array($expected_data)) {
      if (isset($expected_data['filename'])) {
        $this->assertEquals($expected_data['filename'], $commit_data['filename']);
      }
      if (isset($expected_data['fid'])) {
        $this->assertEquals($expected_data['fid'], $commit_data['fid']);
      }
    }


    // Check the complete expected data matches what we got, for return values
    // which are not arrays, and for completeness.
    $this->assertEquals($expected_data, $commit_data);
  }

  /**
   * Data provider for testCommitMessageParser().
   */
  public function providerCommitMessages() {
    return [
      'nothing' => [
        // Message.
        'Some other commit message.',
        // Expected data.
        FALSE,
      ],
      'd.org patch' => [
        // Message.
        'Patch from Drupal.org. Comment: 10; file: myfile.patch; fid 16. Automatic commit by dorgflow.',
        // Expected data.
        [
          'filename' => 'myfile.patch',
          'fid' => 16,
        ],
      ],
      'local commit' => [
        // Message.
        'Patch for Drupal.org. File: myfile.patch. Automatic commit by dorgflow.',
        // Expected data.
        [
          'filename' => 'myfile.patch',
        ],
      ],
    ];
  }

}