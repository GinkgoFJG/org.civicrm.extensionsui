<?php

use CRM_Extensionsui_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;

/**
 * Tests based on the presence of a local CiviDiscount module with metadata that
 * differ from those provided by the microservice.
 *
 * Tips:
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class api_v3_Extensionsui_localExtensionTest extends \PHPUnit_Framework_TestCase implements HeadlessInterface, TransactionalInterface {

  private $extDestination;

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp() {
    parent::setUp();

    $result = civicrm_api3('Extension', 'get', array(
      'key' => 'org.civicrm.module.cividiscount',
      'sequential' => 1,
    ));
    $this->assertEquals(0, $result['count'], "These tests assume CiviDiscount files do not exist locally, but they do.");

    global $civicrm_root;
    $this->extDestination = $civicrm_root . 'tools/extensions/org.civicrm.module.cividiscount';
    mkdir($this->extDestination);

    $fakeInfoXml = E::path('tests/resources/org.civicrm.module.cividiscount/info.xml.fake');
    copy($fakeInfoXml, $this->extDestination . '/info.xml');

    civicrm_api3('Extension', 'refresh', array());
  }

  public function tearDown() {
    parent::tearDown();

    unlink($this->extDestination . '/info.xml');
    rmdir($this->extDestination);
  }

  /**
   * Ensure the API provides local values when they differ from remote values.
   */
  public function testLocalValuesTrumpRemoteValues() {
    $result = civicrm_api3('Extension', 'getCoalesced', array(
      'options' => array(
        'limit' => 0,
      ),
    ));
    $civiDiscount = array_column($result['values'], NULL, 'key')['org.civicrm.module.cividiscount'];

    $this->assertEquals('uninstalled', $civiDiscount['status']);
    $this->assertEquals('CiviFauxDiscount', $civiDiscount['name']);
    $this->assertEquals('Not really CiviDiscount!', $civiDiscount['description']);
    $this->assertArrayHasKey('Test', $civiDiscount['urls']);
    $this->assertEquals('to ill', $civiDiscount['license']);
    $this->assertEquals('Test McTest', $civiDiscount['maintainer']['author']);
    $this->assertEquals('test@example.org', $civiDiscount['maintainer']['email']);
    $this->assertEquals('Peanut gallery.', $civiDiscount['comments']);
    $this->assertEquals($this->extDestination, $civiDiscount['path']);
    $this->assertEquals('', $civiDiscount['statusLabel']);
  }

}