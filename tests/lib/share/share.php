<?php
/**
* ownCloud
*
* @author Michael Gapczynski
* @copyright 2012 Michael Gapczynski mtgap@owncloud.com
*
* This library is free software; you can redistribute it and/or
* modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
* License as published by the Free Software Foundation; either
* version 3 of the License, or any later version.
*
* This library is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU AFFERO GENERAL PUBLIC LICENSE for more details.
*
* You should have received a copy of the GNU Affero General Public
* License along with this library.  If not, see <http://www.gnu.org/licenses/>.
*/

class Test_Share extends Test\TestCase {

	protected $itemType;
	protected $userBackend;
	protected $user1;
	protected $user2;
	protected $user3;
	protected $user4;
	protected $user5;
	protected $groupAndUser;
	protected $groupBackend;
	protected $group1;
	protected $group2;
	protected $resharing;
	protected $dateInFuture;
	protected $dateInPast;

	protected function setUp() {
		parent::setUp();
		OC_User::clearBackends();
		OC_User::useBackend('dummy');
		$this->user1 = $this->getUniqueID('user1_');
		$this->user2 = $this->getUniqueID('user2_');
		$this->user3 = $this->getUniqueID('user3_');
		$this->user4 = $this->getUniqueID('user4_');
		$this->user5 = $this->getUniqueID('user5_');
		$this->groupAndUser = $this->getUniqueID('groupAndUser_');
		OC_User::createUser($this->user1, 'pass');
		OC_User::createUser($this->user2, 'pass');
		OC_User::createUser($this->user3, 'pass');
		OC_User::createUser($this->user4, 'pass');
		OC_User::createUser($this->user5, 'pass'); // no group
		OC_User::createUser($this->groupAndUser, 'pass');
		OC_User::setUserId($this->user1);
		OC_Group::clearBackends();
		OC_Group::useBackend(new OC_Group_Dummy);
		$this->group1 = $this->getUniqueID('group1_');
		$this->group2 = $this->getUniqueID('group2_');
		OC_Group::createGroup($this->group1);
		OC_Group::createGroup($this->group2);
		OC_Group::createGroup($this->groupAndUser);
		OC_Group::addToGroup($this->user1, $this->group1);
		OC_Group::addToGroup($this->user2, $this->group1);
		OC_Group::addToGroup($this->user3, $this->group1);
		OC_Group::addToGroup($this->user2, $this->group2);
		OC_Group::addToGroup($this->user4, $this->group2);
		OC_Group::addToGroup($this->user2, $this->groupAndUser);
		OC_Group::addToGroup($this->user3, $this->groupAndUser);
		OCP\Share::registerBackend('test', 'Test_Share_Backend');
		OC_Hook::clear('OCP\\Share');
		OC::registerShareHooks();
		$this->resharing = OC_Appconfig::getValue('core', 'shareapi_allow_resharing', 'yes');
		OC_Appconfig::setValue('core', 'shareapi_allow_resharing', 'yes');

		// 20 Minutes in the past, 20 minutes in the future.
		$now = time();
		$dateFormat = 'Y-m-d H:i:s';
		$this->dateInPast = date($dateFormat, $now - 20 * 60);
		$this->dateInFuture = date($dateFormat, $now + 20 * 60);
	}

	protected function tearDown() {
		$query = OC_DB::prepare('DELETE FROM `*PREFIX*share` WHERE `item_type` = ?');
		$query->execute(array('test'));
		OC_Appconfig::setValue('core', 'shareapi_allow_resharing', $this->resharing);

		OC_User::deleteUser($this->user1);
		OC_User::deleteUser($this->user2);
		OC_User::deleteUser($this->user3);
		OC_User::deleteUser($this->user4);
		OC_User::deleteUser($this->user5);
		OC_User::deleteUser($this->groupAndUser);

		OC_Group::deleteGroup($this->group1);
		OC_Group::deleteGroup($this->group2);
		OC_Group::deleteGroup($this->groupAndUser);

		parent::tearDown();
	}

	public function testShareInvalidShareType() {
		$message = 'Share type foobar is not valid for test.txt';
		try {
			OCP\Share::shareItem('test', 'test.txt', 'foobar', $this->user2, OCP\PERMISSION_READ);
		} catch (Exception $exception) {
			$this->assertEquals($message, $exception->getMessage());
		}
	}

	public function testInvalidItemType() {
		$message = 'Sharing backend for foobar not found';
		try {
			OCP\Share::shareItem('foobar', 'test.txt', OCP\Share::SHARE_TYPE_USER, $this->user2, OCP\PERMISSION_READ);
			$this->fail('Exception was expected: '.$message);
		} catch (Exception $exception) {
			$this->assertEquals($message, $exception->getMessage());
		}
		try {
			OCP\Share::getItemsSharedWith('foobar');
			$this->fail('Exception was expected: '.$message);
		} catch (Exception $exception) {
			$this->assertEquals($message, $exception->getMessage());
		}
		try {
			OCP\Share::getItemSharedWith('foobar', 'test.txt');
			$this->fail('Exception was expected: '.$message);
		} catch (Exception $exception) {
			$this->assertEquals($message, $exception->getMessage());
		}
		try {
			OCP\Share::getItemSharedWithBySource('foobar', 'test.txt');
			$this->fail('Exception was expected: '.$message);
		} catch (Exception $exception) {
			$this->assertEquals($message, $exception->getMessage());
		}
		try {
			OCP\Share::getItemShared('foobar', 'test.txt');
			$this->fail('Exception was expected: '.$message);
		} catch (Exception $exception) {
			$this->assertEquals($message, $exception->getMessage());
		}
		try {
			OCP\Share::unshare('foobar', 'test.txt', OCP\Share::SHARE_TYPE_USER, $this->user2);
			$this->fail('Exception was expected: '.$message);
		} catch (Exception $exception) {
			$this->assertEquals($message, $exception->getMessage());
		}
		try {
			OCP\Share::setPermissions('foobar', 'test.txt', OCP\Share::SHARE_TYPE_USER, $this->user2, OCP\PERMISSION_UPDATE);
			$this->fail('Exception was expected: '.$message);
		} catch (Exception $exception) {
			$this->assertEquals($message, $exception->getMessage());
		}
	}

	protected function shareUserOneTestFileWithUserTwo() {
		OC_User::setUserId($this->user1);
		$this->assertTrue(
			OCP\Share::shareItem('test', 'test.txt', OCP\Share::SHARE_TYPE_USER, $this->user2, OCP\PERMISSION_READ),
			'Failed asserting that user 1 successfully shared text.txt with user 2.'
		);
		$this->assertContains(
			'test.txt',
			OCP\Share::getItemShared('test', 'test.txt', Test_Share_Backend::FORMAT_SOURCE),
			'Failed asserting that test.txt is a shared file of user 1.'
		);

		OC_User::setUserId($this->user2);
		$this->assertContains(
			'test.txt',
			OCP\Share::getItemSharedWith('test', 'test.txt', Test_Share_Backend::FORMAT_SOURCE),
			'Failed asserting that user 2 has access to test.txt after initial sharing.'
		);
	}

	protected function shareUserTestFileAsLink() {
		OC_User::setUserId($this->user1);
		$result = OCP\Share::shareItem('test', 'test.txt', OCP\Share::SHARE_TYPE_LINK, null, OCP\PERMISSION_READ);
		$this->assertTrue(is_string($result));
	}

	/**
	 * @param string $sharer
	 * @param string $receiver
	 */
	protected function shareUserTestFileWithUser($sharer, $receiver) {
		OC_User::setUserId($sharer);
		$this->assertTrue(
			OCP\Share::shareItem('test', 'test.txt', OCP\Share::SHARE_TYPE_USER, $receiver, OCP\PERMISSION_READ | OCP\PERMISSION_SHARE),
			'Failed asserting that ' . $sharer . ' successfully shared text.txt with ' . $receiver . '.'
		);
		$this->assertContains(
			'test.txt',
			OCP\Share::getItemShared('test', 'test.txt', Test_Share_Backend::FORMAT_SOURCE),
			'Failed asserting that test.txt is a shared file of ' . $sharer . '.'
		);

		OC_User::setUserId($receiver);
		$this->assertContains(
			'test.txt',
			OCP\Share::getItemSharedWith('test', 'test.txt', Test_Share_Backend::FORMAT_SOURCE),
			'Failed asserting that ' . $receiver . ' has access to test.txt after initial sharing.'
		);
	}

	public function testShareWithUser() {
		// Invalid shares
		$message = 'Sharing test.txt failed, because the user '.$this->user1.' is the item owner';
		try {
			OCP\Share::shareItem('test', 'test.txt', OCP\Share::SHARE_TYPE_USER, $this->user1, OCP\PERMISSION_READ);
			$this->fail('Exception was expected: '.$message);
		} catch (Exception $exception) {
			$this->assertEquals($message, $exception->getMessage());
		}
		$message = 'Sharing test.txt failed, because the user foobar does not exist';
		try {
			OCP\Share::shareItem('test', 'test.txt', OCP\Share::SHARE_TYPE_USER, 'foobar', OCP\PERMISSION_READ);
			$this->fail('Exception was expected: '.$message);
		} catch (Exception $exception) {
			$this->assertEquals($message, $exception->getMessage());
		}
		$message = 'Sharing foobar failed, because the sharing backend for test could not find its source';
		try {
			OCP\Share::shareItem('test', 'foobar', OCP\Share::SHARE_TYPE_USER, $this->user2, OCP\PERMISSION_READ);
			$this->fail('Exception was expected: '.$message);
		} catch (Exception $exception) {
			$this->assertEquals($message, $exception->getMessage());
		}

		// Valid share
		$this->shareUserOneTestFileWithUserTwo();

		// Attempt to share again
		OC_User::setUserId($this->user1);
		$message = 'Sharing test.txt failed, because this item is already shared with '.$this->user2;
		try {
			OCP\Share::shareItem('test', 'test.txt', OCP\Share::SHARE_TYPE_USER, $this->user2, OCP\PERMISSION_READ);
			$this->fail('Exception was expected: '.$message);
		} catch (Exception $exception) {
			$this->assertEquals($message, $exception->getMessage());
		}

		// Attempt to share back
		OC_User::setUserId($this->user2);
		$message = 'Sharing test.txt failed, because the user '.$this->user1.' is the original sharer';
		try {
			OCP\Share::shareItem('test', 'test.txt', OCP\Share::SHARE_TYPE_USER, $this->user1, OCP\PERMISSION_READ);
			$this->fail('Exception was expected: '.$message);
		} catch (Exception $exception) {
			$this->assertEquals($message, $exception->getMessage());
		}

		// Unshare
		OC_User::setUserId($this->user1);
		$this->assertTrue(OCP\Share::unshare('test', 'test.txt', OCP\Share::SHARE_TYPE_USER, $this->user2));

		// Attempt reshare without share permission
		$this->assertTrue(OCP\Share::shareItem('test', 'test.txt', OCP\Share::SHARE_TYPE_USER, $this->user2, OCP\PERMISSION_READ));
		OC_User::setUserId($this->user2);
		$message = 'Sharing test.txt failed, because resharing is not allowed';
		try {
			OCP\Share::shareItem('test', 'test.txt', OCP\Share::SHARE_TYPE_USER, $this->user3, OCP\PERMISSION_READ);
			$this->fail('Exception was expected: '.$message);
		} catch (Exception $exception) {
			$this->assertEquals($message, $exception->getMessage());
		}

		// Owner grants share and update permission
		OC_User::setUserId($this->user1);
		$this->assertTrue(OCP\Share::setPermissions('test', 'test.txt', OCP\Share::SHARE_TYPE_USER, $this->user2, OCP\PERMISSION_READ | OCP\PERMISSION_UPDATE | OCP\PERMISSION_SHARE));

		// Attempt reshare with escalated permissions
		OC_User::setUserId($this->user2);
		$message = 'Sharing test.txt failed, because the permissions exceed permissions granted to '.$this->user2;
		try {
			OCP\Share::shareItem('test', 'test.txt', OCP\Share::SHARE_TYPE_USER, $this->user3, OCP\PERMISSION_READ | OCP\PERMISSION_DELETE);
			$this->fail('Exception was expected: '.$message);
		} catch (Exception $exception) {
			$this->assertEquals($message, $exception->getMessage());
		}

		// Valid reshare
		$this->assertTrue(OCP\Share::shareItem('test', 'test.txt', OCP\Share::SHARE_TYPE_USER, $this->user3, OCP\PERMISSION_READ | OCP\PERMISSION_UPDATE));
		$this->assertEquals(array('test.txt'), OCP\Share::getItemShared('test', 'test.txt', Test_Share_Backend::FORMAT_SOURCE));
		OC_User::setUserId($this->user3);
		$this->assertEquals(array('test.txt'), OCP\Share::getItemSharedWith('test', 'test.txt', Test_Share_Backend::FORMAT_SOURCE));
		$this->assertEquals(array(OCP\PERMISSION_READ | OCP\PERMISSION_UPDATE), OCP\Share::getItemSharedWith('test', 'test.txt', Test_Share_Backend::FORMAT_PERMISSIONS));

		// Attempt to escalate permissions
		OC_User::setUserId($this->user2);
		$message = 'Setting permissions for test.txt failed, because the permissions exceed permissions granted to '.$this->user2;
		try {
			OCP\Share::setPermissions('test', 'test.txt', OCP\Share::SHARE_TYPE_USER, $this->user3, OCP\PERMISSION_READ | OCP\PERMISSION_DELETE);
			$this->fail('Exception was expected: '.$message);
		} catch (Exception $exception) {
			$this->assertEquals($message, $exception->getMessage());
		}

		// Remove update permission
		OC_User::setUserId($this->user1);
		$this->assertTrue(OCP\Share::setPermissions('test', 'test.txt', OCP\Share::SHARE_TYPE_USER, $this->user2, OCP\PERMISSION_READ | OCP\PERMISSION_SHARE));
		OC_User::setUserId($this->user2);
		$this->assertEquals(array(OCP\PERMISSION_READ | OCP\PERMISSION_SHARE), OCP\Share::getItemSharedWith('test', 'test.txt', Test_Share_Backend::FORMAT_PERMISSIONS));
		OC_User::setUserId($this->user3);
		$this->assertEquals(array(OCP\PERMISSION_READ), OCP\Share::getItemSharedWith('test', 'test.txt', Test_Share_Backend::FORMAT_PERMISSIONS));

		// Remove share permission
		OC_User::setUserId($this->user1);
		$this->assertTrue(OCP\Share::setPermissions('test', 'test.txt', OCP\Share::SHARE_TYPE_USER, $this->user2, OCP\PERMISSION_READ));
		OC_User::setUserId($this->user2);
		$this->assertEquals(array(OCP\PERMISSION_READ), OCP\Share::getItemSharedWith('test', 'test.txt', Test_Share_Backend::FORMAT_PERMISSIONS));
		OC_User::setUserId($this->user3);
		$this->assertSame(array(), OCP\Share::getItemSharedWith('test', 'test.txt'));

		// Reshare again, and then have owner unshare
		OC_User::setUserId($this->user1);
		$this->assertTrue(OCP\Share::setPermissions('test', 'test.txt', OCP\Share::SHARE_TYPE_USER, $this->user2, OCP\PERMISSION_READ | OCP\PERMISSION_SHARE));
		OC_User::setUserId($this->user2);
		$this->assertTrue(OCP\Share::shareItem('test', 'test.txt', OCP\Share::SHARE_TYPE_USER, $this->user3, OCP\PERMISSION_READ));
		OC_User::setUserId($this->user1);
		$this->assertTrue(OCP\Share::unshare('test', 'test.txt', OCP\Share::SHARE_TYPE_USER, $this->user2));
		OC_User::setUserId($this->user2);
		$this->assertSame(array(), OCP\Share::getItemSharedWith('test', 'test.txt'));
		OC_User::setUserId($this->user3);
		$this->assertSame(array(), OCP\Share::getItemSharedWith('test', 'test.txt'));

		// Attempt target conflict
		OC_User::setUserId($this->user1);
		$this->assertTrue(OCP\Share::shareItem('test', 'test.txt', OCP\Share::SHARE_TYPE_USER, $this->user2, OCP\PERMISSION_READ));
		OC_User::setUserId($this->user3);
		$this->assertTrue(OCP\Share::shareItem('test', 'share.txt', OCP\Share::SHARE_TYPE_USER, $this->user2, OCP\PERMISSION_READ));

		OC_User::setUserId($this->user2);
		$to_test = OCP\Share::getItemsSharedWith('test', Test_Share_Backend::FORMAT_TARGET);
		$this->assertEquals(2, count($to_test));
		$this->assertTrue(in_array('test.txt', $to_test));
		$this->assertTrue(in_array('test1.txt', $to_test));

		// Remove user
		OC_User::setUserId($this->user1);
		OC_User::deleteUser($this->user1);
		OC_User::setUserId($this->user2);
		$this->assertEquals(array('test1.txt'), OCP\Share::getItemsSharedWith('test', Test_Share_Backend::FORMAT_TARGET));
	}

	public function testShareWithUserExpirationExpired() {
		OC_User::setUserId($this->user1);
		$this->shareUserOneTestFileWithUserTwo();
		$this->shareUserTestFileAsLink();

		// manipulate share table and set expire date to the past
		$query = \OC_DB::prepare('UPDATE `*PREFIX*share` SET `expiration` = ? WHERE `item_type` = ? AND `item_source` = ?  AND `uid_owner` = ? AND `share_type` = ?');
		$query->bindValue(1, new \DateTime($this->dateInPast), 'datetime');
		$query->bindValue(2, 'test');
		$query->bindValue(3, 'test.txt');
		$query->bindValue(4, $this->user1);
		$query->bindValue(5, \OCP\Share::SHARE_TYPE_LINK);
		$query->execute();

		$shares = OCP\Share::getItemsShared('test');
		$this->assertSame(1, count($shares));
		$share = reset($shares);
		$this->assertSame(\OCP\Share::SHARE_TYPE_USER, $share['share_type']);
	}

	public function testSetExpireDateInPast() {
		OC_User::setUserId($this->user1);
		$this->shareUserOneTestFileWithUserTwo();
		$this->shareUserTestFileAsLink();

		$setExpireDateFailed = false;
		try {
			$this->assertTrue(
					OCP\Share::setExpirationDate('test', 'test.txt', $this->dateInPast, ''),
					'Failed asserting that user 1 successfully set an expiration date for the test.txt share.'
			);
		} catch (\Exception $e) {
			$setExpireDateFailed = true;
		}

		$this->assertTrue($setExpireDateFailed);
	}

	public function testShareWithUserExpirationValid() {
		OC_User::setUserId($this->user1);
		$this->shareUserOneTestFileWithUserTwo();
		$this->shareUserTestFileAsLink();


		$this->assertTrue(
			OCP\Share::setExpirationDate('test', 'test.txt', $this->dateInFuture, ''),
			'Failed asserting that user 1 successfully set an expiration date for the test.txt share.'
		);

		$shares = OCP\Share::getItemsShared('test');
		$this->assertSame(2, count($shares));

	}

	/*
	 * if user is in a group excluded from resharing, then the share permission should
	 * be removed
	 */
	public function testShareWithUserAndUserIsExcludedFromResharing() {

		OC_User::setUserId($this->user1);
		$this->assertTrue(
			OCP\Share::shareItem('test', 'test.txt', OCP\Share::SHARE_TYPE_USER, $this->user4, OCP\PERMISSION_ALL),
			'Failed asserting that user 1 successfully shared text.txt with user 4.'
		);
		$this->assertContains(
			'test.txt',
			OCP\Share::getItemShared('test', 'test.txt', Test_Share_Backend::FORMAT_SOURCE),
			'Failed asserting that test.txt is a shared file of user 1.'
		);

		// exclude group2 from sharing
		\OC_Appconfig::setValue('core', 'shareapi_exclude_groups_list', $this->group2);
		\OC_Appconfig::setValue('core', 'shareapi_exclude_groups', "yes");

		OC_User::setUserId($this->user4);

		$share = OCP\Share::getItemSharedWith('test', 'test.txt');

		$this->assertSame(\OCP\PERMISSION_ALL & ~OCP\PERMISSION_SHARE, $share['permissions'],
				'Failed asserting that user 4 is excluded from re-sharing');

		\OC_Appconfig::deleteKey('core', 'shareapi_exclude_groups_list');
		\OC_Appconfig::deleteKey('core', 'shareapi_exclude_groups');

	}

	protected function shareUserOneTestFileWithGroupOne() {
		OC_User::setUserId($this->user1);
		$this->assertTrue(
			OCP\Share::shareItem('test', 'test.txt', OCP\Share::SHARE_TYPE_GROUP, $this->group1, OCP\PERMISSION_READ),
			'Failed asserting that user 1 successfully shared text.txt with group 1.'
		);
		$this->assertContains(
			'test.txt',
			OCP\Share::getItemShared('test', 'test.txt', Test_Share_Backend::FORMAT_SOURCE),
			'Failed asserting that test.txt is a shared file of user 1.'
		);

		OC_User::setUserId($this->user2);
		$this->assertContains(
			'test.txt',
			OCP\Share::getItemSharedWith('test', 'test.txt', Test_Share_Backend::FORMAT_SOURCE),
			'Failed asserting that user 2 has access to test.txt after initial sharing.'
		);

		OC_User::setUserId($this->user3);
		$this->assertContains(
			'test.txt',
			OCP\Share::getItemSharedWith('test', 'test.txt', Test_Share_Backend::FORMAT_SOURCE),
			'Failed asserting that user 3 has access to test.txt after initial sharing.'
		);
	}

	public function testShareWithGroup() {
		// Invalid shares
		$message = 'Sharing test.txt failed, because the group foobar does not exist';
		try {
			OCP\Share::shareItem('test', 'test.txt', OCP\Share::SHARE_TYPE_GROUP, 'foobar', OCP\PERMISSION_READ);
			$this->fail('Exception was expected: '.$message);
		} catch (Exception $exception) {
			$this->assertEquals($message, $exception->getMessage());
		}
		$policy = OC_Appconfig::getValue('core', 'shareapi_only_share_with_group_members', 'no');
		OC_Appconfig::setValue('core', 'shareapi_only_share_with_group_members', 'yes');
		$message = 'Sharing test.txt failed, because '.$this->user1.' is not a member of the group '.$this->group2;
		try {
			OCP\Share::shareItem('test', 'test.txt', OCP\Share::SHARE_TYPE_GROUP, $this->group2, OCP\PERMISSION_READ);
			$this->fail('Exception was expected: '.$message);
		} catch (Exception $exception) {
			$this->assertEquals($message, $exception->getMessage());
		}
		OC_Appconfig::setValue('core', 'shareapi_only_share_with_group_members', $policy);

		// Valid share
		$this->shareUserOneTestFileWithGroupOne();

		// Attempt to share again
		OC_User::setUserId($this->user1);
		$message = 'Sharing test.txt failed, because this item is already shared with '.$this->group1;
		try {
			OCP\Share::shareItem('test', 'test.txt', OCP\Share::SHARE_TYPE_GROUP, $this->group1, OCP\PERMISSION_READ);
			$this->fail('Exception was expected: '.$message);
		} catch (Exception $exception) {
			$this->assertEquals($message, $exception->getMessage());
		}

		// Attempt to share back to owner of group share
		OC_User::setUserId($this->user2);
		$message = 'Sharing test.txt failed, because the user '.$this->user1.' is the original sharer';
		try {
			OCP\Share::shareItem('test', 'test.txt', OCP\Share::SHARE_TYPE_USER, $this->user1, OCP\PERMISSION_READ);
			$this->fail('Exception was expected: '.$message);
		} catch (Exception $exception) {
			$this->assertEquals($message, $exception->getMessage());
		}

		// Attempt to share back to group
		$message = 'Sharing test.txt failed, because this item is already shared with '.$this->group1;
		try {
			OCP\Share::shareItem('test', 'test.txt', OCP\Share::SHARE_TYPE_GROUP, $this->group1, OCP\PERMISSION_READ);
			$this->fail('Exception was expected: '.$message);
		} catch (Exception $exception) {
			$this->assertEquals($message, $exception->getMessage());
		}

		// Attempt to share back to member of group
		$message ='Sharing test.txt failed, because this item is already shared with '.$this->user3;
		try {
			OCP\Share::shareItem('test', 'test.txt', OCP\Share::SHARE_TYPE_USER, $this->user3, OCP\PERMISSION_READ);
			$this->fail('Exception was expected: '.$message);
		} catch (Exception $exception) {
			$this->assertEquals($message, $exception->getMessage());
		}

		// Unshare
		OC_User::setUserId($this->user1);
		$this->assertTrue(OCP\Share::unshare('test', 'test.txt', OCP\Share::SHARE_TYPE_GROUP, $this->group1));

		// Valid share with same person - user then group
		$this->assertTrue(OCP\Share::shareItem('test', 'test.txt', OCP\Share::SHARE_TYPE_USER, $this->user2, OCP\PERMISSION_READ | OCP\PERMISSION_DELETE | OCP\PERMISSION_SHARE));
		$this->assertTrue(OCP\Share::shareItem('test', 'test.txt', OCP\Share::SHARE_TYPE_GROUP, $this->group1, OCP\PERMISSION_READ | OCP\PERMISSION_UPDATE));
		OC_User::setUserId($this->user2);
		$this->assertEquals(array('test.txt'), OCP\Share::getItemsSharedWith('test', Test_Share_Backend::FORMAT_TARGET));
		$this->assertEquals(array(OCP\PERMISSION_READ | OCP\PERMISSION_UPDATE | OCP\PERMISSION_DELETE | OCP\PERMISSION_SHARE), OCP\Share::getItemSharedWith('test', 'test.txt', Test_Share_Backend::FORMAT_PERMISSIONS));
		OC_User::setUserId($this->user3);
		$this->assertEquals(array('test.txt'), OCP\Share::getItemsSharedWith('test', Test_Share_Backend::FORMAT_TARGET));
		$this->assertEquals(array(OCP\PERMISSION_READ | OCP\PERMISSION_UPDATE), OCP\Share::getItemSharedWith('test', 'test.txt', Test_Share_Backend::FORMAT_PERMISSIONS));

		// Valid reshare
		OC_User::setUserId($this->user2);
		$this->assertTrue(OCP\Share::shareItem('test', 'test.txt', OCP\Share::SHARE_TYPE_USER, $this->user4, OCP\PERMISSION_READ));
		OC_User::setUserId($this->user4);
		$this->assertEquals(array('test.txt'), OCP\Share::getItemsSharedWith('test', Test_Share_Backend::FORMAT_TARGET));

		// Unshare from user only
		OC_User::setUserId($this->user1);
		$this->assertTrue(OCP\Share::unshare('test', 'test.txt', OCP\Share::SHARE_TYPE_USER, $this->user2));
		OC_User::setUserId($this->user2);
		$this->assertEquals(array(OCP\PERMISSION_READ | OCP\PERMISSION_UPDATE), OCP\Share::getItemSharedWith('test', 'test.txt', Test_Share_Backend::FORMAT_PERMISSIONS));
		OC_User::setUserId($this->user4);
		$this->assertEquals(array('test.txt'), OCP\Share::getItemsSharedWith('test', Test_Share_Backend::FORMAT_TARGET));

		// Valid share with same person - group then user
		OC_User::setUserId($this->user1);
		$this->assertTrue(OCP\Share::shareItem('test', 'test.txt', OCP\Share::SHARE_TYPE_USER, $this->user2, OCP\PERMISSION_READ | OCP\PERMISSION_DELETE));
		OC_User::setUserId($this->user2);
		$this->assertEquals(array('test.txt'), OCP\Share::getItemsSharedWith('test', Test_Share_Backend::FORMAT_TARGET));
		$this->assertEquals(array(OCP\PERMISSION_READ | OCP\PERMISSION_UPDATE | OCP\PERMISSION_DELETE), OCP\Share::getItemSharedWith('test', 'test.txt', Test_Share_Backend::FORMAT_PERMISSIONS));

		// Unshare from group only
		OC_User::setUserId($this->user1);
		$this->assertTrue(OCP\Share::unshare('test', 'test.txt', OCP\Share::SHARE_TYPE_GROUP, $this->group1));
		OC_User::setUserId($this->user2);
		$this->assertEquals(array(OCP\PERMISSION_READ | OCP\PERMISSION_DELETE), OCP\Share::getItemSharedWith('test', 'test.txt', Test_Share_Backend::FORMAT_PERMISSIONS));

		// Attempt user specific target conflict
		OC_User::setUserId($this->user3);
		$this->assertTrue(OCP\Share::shareItem('test', 'share.txt', OCP\Share::SHARE_TYPE_GROUP, $this->group1, OCP\PERMISSION_READ | OCP\PERMISSION_SHARE));
		OC_User::setUserId($this->user2);
		$to_test = OCP\Share::getItemsSharedWith('test', Test_Share_Backend::FORMAT_TARGET);
		$this->assertEquals(2, count($to_test));
		$this->assertTrue(in_array('test.txt', $to_test));
		$this->assertTrue(in_array('test1.txt', $to_test));

		// Valid reshare
		$this->assertTrue(OCP\Share::shareItem('test', 'share.txt', OCP\Share::SHARE_TYPE_USER, $this->user4, OCP\PERMISSION_READ | OCP\PERMISSION_SHARE));
		OC_User::setUserId($this->user4);
		$this->assertEquals(array('test1.txt'), OCP\Share::getItemsSharedWith('test', Test_Share_Backend::FORMAT_TARGET));

		// Remove user from group
		OC_Group::removeFromGroup($this->user2, $this->group1);
		OC_User::setUserId($this->user2);
		$this->assertEquals(array('test.txt'), OCP\Share::getItemsSharedWith('test', Test_Share_Backend::FORMAT_TARGET));
		OC_User::setUserId($this->user4);
		$this->assertEquals(array(), OCP\Share::getItemsSharedWith('test', Test_Share_Backend::FORMAT_TARGET));

		// Add user to group
		OC_Group::addToGroup($this->user4, $this->group1);
		$this->assertEquals(array('test.txt'), OCP\Share::getItemsSharedWith('test', Test_Share_Backend::FORMAT_TARGET));

		// Unshare from self
		$this->assertTrue(OCP\Share::unshareFromSelf('test', 'test.txt'));
		$this->assertEquals(array(), OCP\Share::getItemsSharedWith('test', Test_Share_Backend::FORMAT_TARGET));
		OC_User::setUserId($this->user2);
		$this->assertEquals(array('test.txt'), OCP\Share::getItemsSharedWith('test', Test_Share_Backend::FORMAT_TARGET));

		// Remove group
		OC_Group::deleteGroup($this->group1);
		OC_User::setUserId($this->user4);
		$this->assertEquals(array(), OCP\Share::getItemsSharedWith('test', Test_Share_Backend::FORMAT_TARGET));
		OC_User::setUserId($this->user3);
		$this->assertEquals(array(), OCP\Share::getItemsShared('test'));
	}


	public function testShareWithGroupAndUserBothHaveTheSameId() {

		$this->shareUserTestFileWithUser($this->user1, $this->groupAndUser);

		OC_User::setUserId($this->groupAndUser);

		$this->assertEquals(array('test.txt'), OCP\Share::getItemSharedWith('test', 'test.txt', Test_Share_Backend::FORMAT_SOURCE),
				'"groupAndUser"-User does not see the file but it was shared with him');

		OC_User::setUserId($this->user2);
		$this->assertEquals(array(), OCP\Share::getItemSharedWith('test', 'test.txt', Test_Share_Backend::FORMAT_SOURCE),
				'User2 sees test.txt but it was only shared with the user "groupAndUser" and not with group');

		OC_User::setUserId($this->user1);
		$this->assertTrue(OCP\Share::unshareAll('test', 'test.txt'));

		$this->assertTrue(
				OCP\Share::shareItem('test', 'test.txt', OCP\Share::SHARE_TYPE_GROUP, $this->groupAndUser, OCP\PERMISSION_READ),
				'Failed asserting that user 1 successfully shared text.txt with group 1.'
		);

		OC_User::setUserId($this->groupAndUser);
		$this->assertEquals(array(), OCP\Share::getItemSharedWith('test', 'test.txt', Test_Share_Backend::FORMAT_SOURCE),
				'"groupAndUser"-User sees test.txt but it was only shared with the group "groupAndUser" and not with the user');

		OC_User::setUserId($this->user2);
		$this->assertEquals(array('test.txt'), OCP\Share::getItemSharedWith('test', 'test.txt', Test_Share_Backend::FORMAT_SOURCE),
				'User2 does not see test.txt but it was shared with the group "groupAndUser"');

		OC_User::setUserId($this->user1);
		$this->assertTrue(OCP\Share::unshareAll('test', 'test.txt'));

	}

	/**
	 * @param boolean|string $token
	 */
	protected function getShareByValidToken($token) {
		$row = OCP\Share::getShareByToken($token);
		$this->assertInternalType(
			'array',
			$row,
			"Failed asserting that a share for token $token exists."
		);
		return $row;
	}

	public function testGetItemSharedWithUser() {
		OC_User::setUserId($this->user1);

		//add dummy values to the share table
		$query = \OC_DB::prepare('INSERT INTO `*PREFIX*share` ('
			.' `item_type`, `item_source`, `item_target`, `share_type`,'
			.' `share_with`, `uid_owner`) VALUES (?,?,?,?,?,?)');
		$args = array('test', 99, 'target1', OCP\Share::SHARE_TYPE_USER, $this->user2, $this->user1);
		$query->execute($args);
		$args = array('test', 99, 'target2', OCP\Share::SHARE_TYPE_USER, $this->user4, $this->user1);
		$query->execute($args);
		$args = array('test', 99, 'target3', OCP\Share::SHARE_TYPE_USER, $this->user3, $this->user2);
		$query->execute($args);
		$args = array('test', 99, 'target4', OCP\Share::SHARE_TYPE_USER, $this->user3, $this->user4);
		$query->execute($args);
		$args = array('test', 99, 'target4', OCP\Share::SHARE_TYPE_USER, $this->user5, $this->user4);
		$query->execute($args);


		$result1 = \OCP\Share::getItemSharedWithUser('test', 99, $this->user2);
		$this->assertSame(1, count($result1));
		$this->verifyResult($result1, array('target1'));

		$result2 = \OCP\Share::getItemSharedWithUser('test', 99, null);
		$this->assertSame(5, count($result2));
		$this->verifyResult($result2, array('target1', 'target2'));

		$result3 = \OCP\Share::getItemSharedWithUser('test', 99, null);
		$this->assertSame(5, count($result3)); // 5 because target4 appears twice
		$this->verifyResult($result3, array('target1', 'target2', 'target3', 'target4'));

		$result5 = \OCP\Share::getItemSharedWithUser('test', 99, $this->user5);
		$this->assertSame(1, count($result5));
		$this->verifyResult($result5, array('target4'));
	}

	public function testGetItemSharedWithUserFromGroupShare() {
		OC_User::setUserId($this->user1);

		//add dummy values to the share table
		$query = \OC_DB::prepare('INSERT INTO `*PREFIX*share` ('
			.' `item_type`, `item_source`, `item_target`, `share_type`,'
			.' `share_with`, `uid_owner`) VALUES (?,?,?,?,?,?)');
		$args = array('test', 99, 'target1', OCP\Share::SHARE_TYPE_GROUP, $this->group1, $this->user1);
		$query->execute($args);
		$args = array('test', 99, 'target2', OCP\Share::SHARE_TYPE_GROUP, $this->group2, $this->user1);
		$query->execute($args);
		$args = array('test', 99, 'target3', OCP\Share::SHARE_TYPE_GROUP, $this->group1, $this->user2);
		$query->execute($args);
		$args = array('test', 99, 'target4', OCP\Share::SHARE_TYPE_GROUP, $this->group1, $this->user4);
		$query->execute($args);

		// user2 is in group1 and group2
		$result1 = \OCP\Share::getItemSharedWithUser('test', 99, $this->user2);
		$this->assertSame(4, count($result1));
		$this->verifyResult($result1, array('target1', 'target2', 'target3', 'target4'));

		$result2 = \OCP\Share::getItemSharedWithUser('test', 99, null);
		$this->assertSame(4, count($result2));
		$this->verifyResult($result2, array('target1', 'target2', 'target3', 'target4'));

		$result3 = \OCP\Share::getItemSharedWithUser('test', 99, $this->user5);
		$this->assertSame(0, count($result3));
	}

	public function verifyResult($result, $expected) {
		foreach ($result as $r) {
			if (in_array($r['item_target'], $expected)) {
				$key = array_search($r['item_target'], $expected);
				unset($expected[$key]);
			}
		}
		$this->assertEmpty($expected, 'did not found all expected values');
	}

	public function testShareItemWithLink() {
		OC_User::setUserId($this->user1);
		$token = OCP\Share::shareItem('test', 'test.txt', OCP\Share::SHARE_TYPE_LINK, null, OCP\PERMISSION_READ);
		$this->assertInternalType(
			'string',
			$token,
			'Failed asserting that user 1 successfully shared text.txt as link with token.'
		);

		// testGetShareByTokenNoExpiration
		$row = $this->getShareByValidToken($token);
		$this->assertEmpty(
			$row['expiration'],
			'Failed asserting that the returned row does not have an expiration date.'
		);

		// testGetShareByTokenExpirationValid
		$this->assertTrue(
			OCP\Share::setExpirationDate('test', 'test.txt', $this->dateInFuture, ''),
			'Failed asserting that user 1 successfully set a future expiration date for the test.txt share.'
		);
		$row = $this->getShareByValidToken($token);
		$this->assertNotEmpty(
			$row['expiration'],
			'Failed asserting that the returned row has an expiration date.'
		);

		// manipulate share table and set expire date to the past
		$query = \OC_DB::prepare('UPDATE `*PREFIX*share` SET `expiration` = ? WHERE `item_type` = ? AND `item_source` = ?  AND `uid_owner` = ? AND `share_type` = ?');
		$query->bindValue(1, new \DateTime($this->dateInPast), 'datetime');
		$query->bindValue(2, 'test');
		$query->bindValue(3, 'test.txt');
		$query->bindValue(4, $this->user1);
		$query->bindValue(5, \OCP\Share::SHARE_TYPE_LINK);
		$query->execute();

		$this->assertFalse(
			OCP\Share::getShareByToken($token),
			'Failed asserting that an expired share could not be found.'
		);
	}

	public function testShareItemWithLinkAndDefaultExpireDate() {
		OC_User::setUserId($this->user1);

		\OC_Appconfig::setValue('core', 'shareapi_default_expire_date', 'yes');
		\OC_Appconfig::setValue('core', 'shareapi_expire_after_n_days', '2');

		$token = OCP\Share::shareItem('test', 'test.txt', OCP\Share::SHARE_TYPE_LINK, null, OCP\PERMISSION_READ);
		$this->assertInternalType(
			'string',
			$token,
			'Failed asserting that user 1 successfully shared text.txt as link with token.'
		);

		// share should have default expire date

		$row = $this->getShareByValidToken($token);
		$this->assertNotEmpty(
			$row['expiration'],
			'Failed asserting that the returned row has an default expiration date.'
		);

		\OC_Appconfig::deleteKey('core', 'shareapi_default_expire_date');
		\OC_Appconfig::deleteKey('core', 'shareapi_expire_after_n_days');

	}

	public function testUnshareAll() {
		$this->shareUserTestFileWithUser($this->user1, $this->user2);
		$this->shareUserTestFileWithUser($this->user2, $this->user3);
		$this->shareUserTestFileWithUser($this->user3, $this->user4);
		$this->shareUserOneTestFileWithGroupOne();

		OC_User::setUserId($this->user1);
		$this->assertEquals(
			array('test.txt', 'test.txt'),
			OCP\Share::getItemsShared('test', Test_Share_Backend::FORMAT_SOURCE),
			'Failed asserting that the test.txt file is shared exactly two times by user1.'
		);

		OC_User::setUserId($this->user2);
		$this->assertEquals(
			array('test.txt'),
			OCP\Share::getItemsShared('test', Test_Share_Backend::FORMAT_SOURCE),
			'Failed asserting that the test.txt file is shared exactly once by user2.'
		);

		OC_User::setUserId($this->user3);
		$this->assertEquals(
			array('test.txt'),
			OCP\Share::getItemsShared('test', Test_Share_Backend::FORMAT_SOURCE),
			'Failed asserting that the test.txt file is shared exactly once by user3.'
		);

		$this->assertTrue(
			OCP\Share::unshareAll('test', 'test.txt'),
			'Failed asserting that user 3 successfully unshared all shares of the test.txt share.'
		);

		$this->assertEquals(
			array(),
			OCP\Share::getItemsShared('test'),
			'Failed asserting that the share of the test.txt file by user 3 has been removed.'
		);

		OC_User::setUserId($this->user1);
		$this->assertEquals(
			array(),
			OCP\Share::getItemsShared('test'),
			'Failed asserting that both shares of the test.txt file by user 1 have been removed.'
		);

		OC_User::setUserId($this->user2);
		$this->assertEquals(
			array(),
			OCP\Share::getItemsShared('test'),
			'Failed asserting that the share of the test.txt file by user 2 has been removed.'
		);
	}

	/**
	 * @dataProvider checkPasswordProtectedShareDataProvider
	 * @param $expected
	 * @param $item
	 */
	public function testCheckPasswordProtectedShare($expected, $item) {
		\OC::$session->set('public_link_authenticated', 100);
		$result = \OCP\Share::checkPasswordProtectedShare($item);
		$this->assertEquals($expected, $result);
	}

	function checkPasswordProtectedShareDataProvider() {
		return array(
			array(true, array()),
			array(true, array('share_with' => null)),
			array(true, array('share_with' => '')),
			array(true, array('share_with' => '1234567890', 'share_type' => '1')),
			array(true, array('share_with' => '1234567890', 'share_type' => 1)),
			array(true, array('share_with' => '1234567890', 'share_type' => '3', 'id' => 100)),
			array(true, array('share_with' => '1234567890', 'share_type' => 3, 'id' => 100)),
			array(false, array('share_with' => '1234567890', 'share_type' => '3', 'id' => 101)),
			array(false, array('share_with' => '1234567890', 'share_type' => 3, 'id' => 101)),
		);
	}

	/**
	 * @dataProvider dataProviderTestGroupItems
	 * @param type $ungrouped
	 * @param type $grouped
	 */
	function testGroupItems($ungrouped, $grouped) {

		$result = DummyShareClass::groupItemsTest($ungrouped);

		$this->compareArrays($grouped, $result);

	}

	function compareArrays($result, $expectedResult) {
		foreach ($expectedResult as $key => $value) {
			if (is_array($value)) {
				$this->compareArrays($result[$key], $value);
			} else {
				$this->assertSame($value, $result[$key]);
			}
		}
	}

	function dataProviderTestGroupItems() {
		return array(
			// one array with one share
			array(
				array( // input
					array('item_source' => 1, 'permissions' => \OCP\PERMISSION_ALL, 'item_target' => 't1')),
				array( // expected result
					array('item_source' => 1, 'permissions' => \OCP\PERMISSION_ALL, 'item_target' => 't1'))),
			// two shares both point to the same source
			array(
				array( // input
					array('item_source' => 1, 'permissions' => \OCP\PERMISSION_READ, 'item_target' => 't1'),
					array('item_source' => 1, 'permissions' => \OCP\PERMISSION_UPDATE, 'item_target' => 't1'),
					),
				array( // expected result
					array('item_source' => 1, 'permissions' => \OCP\PERMISSION_READ | \OCP\PERMISSION_UPDATE, 'item_target' => 't1',
						'grouped' => array(
							array('item_source' => 1, 'permissions' => \OCP\PERMISSION_READ, 'item_target' => 't1'),
							array('item_source' => 1, 'permissions' => \OCP\PERMISSION_UPDATE, 'item_target' => 't1'),
							)
						),
					)
				),
			// two shares both point to the same source but with different targets
			array(
				array( // input
					array('item_source' => 1, 'permissions' => \OCP\PERMISSION_READ, 'item_target' => 't1'),
					array('item_source' => 1, 'permissions' => \OCP\PERMISSION_UPDATE, 'item_target' => 't2'),
					),
				array( // expected result
					array('item_source' => 1, 'permissions' => \OCP\PERMISSION_READ, 'item_target' => 't1'),
					array('item_source' => 1, 'permissions' => \OCP\PERMISSION_UPDATE, 'item_target' => 't2'),
					)
				),
			// three shares two point to the same source
			array(
				array( // input
					array('item_source' => 1, 'permissions' => \OCP\PERMISSION_READ, 'item_target' => 't1'),
					array('item_source' => 2, 'permissions' => \OCP\PERMISSION_CREATE, 'item_target' => 't2'),
					array('item_source' => 1, 'permissions' => \OCP\PERMISSION_UPDATE, 'item_target' => 't1'),
					),
				array( // expected result
					array('item_source' => 1, 'permissions' => \OCP\PERMISSION_READ | \OCP\PERMISSION_UPDATE, 'item_target' => 't1',
						'grouped' => array(
							array('item_source' => 1, 'permissions' => \OCP\PERMISSION_READ, 'item_target' => 't1'),
							array('item_source' => 1, 'permissions' => \OCP\PERMISSION_UPDATE, 'item_target' => 't1'),
							)
						),
					array('item_source' => 2, 'permissions' => \OCP\PERMISSION_CREATE, 'item_target' => 't2'),
					)
				),
		);
	}
}

class DummyShareClass extends \OC\Share\Share {
	public static function groupItemsTest($items) {
		return parent::groupItems($items, 'test');
	}
}
