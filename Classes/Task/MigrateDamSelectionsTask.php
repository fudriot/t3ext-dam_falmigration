<?php
namespace TYPO3\CMS\DamFalmigration\Task;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Frans Saris <franssaris@gmail.com>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
use TYPO3\CMS\Core\Utility\GeneralUtility;

class MigrateDamSelectionsTask extends AbstractTask {

	/**
	 * main function, needs to return TRUE or FALSE in order to tell
	 * the scheduler whether the task went through smoothly
	 *
	 * @return boolean
	 */
	public function execute() {
		$this->init();

		$damSelections = $this->getNotMigratedDamSelections();

		// search for txdamFolder and create new folder based sys_file_collection
		foreach ($damSelections as $damSelection) {
			$damFolder = $this->getDamFolder($damSelection);
			if ($damFolder !== FALSE) {
				$damFolder = substr($damFolder, strpos($damFolder, '/fileadmin') + 10);

				$sysFileCollection = array(
					'pid' => $damSelection['pid'],
					'tstamp' => $damSelection['tstamp'],
					'crdate' => $damSelection['crdate'],
					'cruser_id' => $damSelection['cruser_id'],
					'hidden' => $damSelection['hidden'],
					'starttime' => $damSelection['starttime'],
					'endtime' => $damSelection['endtime'],
					'title' => $damSelection['title'],
					'storage' => 1,
					'description' => $damSelection['description'],
					'type' => 'folder',
					'folder' => $damFolder,
					'_migrateddamselectionuid' => $damSelection['uid']
				);
				$this->database->exec_INSERTquery('sys_file_collection', $sysFileCollection);

				$this->amountOfMigratedRecords++;
			}
		}
		
		$this->addResultMessage();
		
		return TRUE;
	}

	/**
	 * get dam folder
	 *
	 * @param array $damSelection
	 * @return bool|string
	 */
	protected function getDamFolder(array $damSelection) {
		$damFolder = FALSE;
		$damSelectionDefinition = unserialize($damSelection['definition']);

		foreach ($damSelectionDefinition as $damSelectionElements) {
			if (array_key_exists('txdamFolder', $damSelectionElements)) {
				$damFolder = key($damSelectionElements['txdamFolder']);
				break;
			}
		}
		return $damFolder;
	}

	/**
	 * get comma separated list of already migrated dam selections
	 * This method checked this with help of col: _migrateddamselectionuid
	 *
	 * @return string
	 */
	protected function getUidListOfAlreadyMigratedSelections() {
		list($migratedRecords) = $this->database->exec_SELECTgetRows(
			'GROUP_CONCAT( _migrateddamselectionuid ) AS uidList',
			'sys_file_collection',
			'_migrateddamselectionuid > 0 AND deleted = 0'
		);
		if (!empty($migratedRecords['uidList'])) {
			return $migratedRecords['uidList'];
		} else return '';
	}

	/**
	 * this method generates an additional where clause to find all dam selections
	 * which were not already migrated
	 *
	 * @return string
	 */
	protected function getAdditionalWhereClauseForNotMigratedDamSelections() {
		$uidList = $this->getUidListOfAlreadyMigratedSelections();
		if ($uidList) {
			$additionalWhereClause = 'AND uid NOT IN (' . $uidList . ')';
		} else $additionalWhereClause = '';
		return $additionalWhereClause;
	}

	/**
	 * get all dam selections which have not been migrated yet
	 *
	 * @return array
	 */
	protected function getNotMigratedDamSelections() {
		$rows = $this->database->exec_SELECTgetRows(
			'*',
			'tx_dam_selection',
			'type = 0 AND deleted = 0 ' . $this->getAdditionalWhereClauseForNotMigratedDamSelections()
		);
		return $rows;
	}

}