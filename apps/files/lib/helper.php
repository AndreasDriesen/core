<?php
/**
 * Copyright (c) 2014 Vincent Petry <pvince81@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCA\Files;

/**
 * Helper class for manipulating file information
 */
class Helper
{
	public static function buildFileStorageStatistics($dir) {
		// information about storage capacities
		$storageInfo = \OC_Helper::getStorageInfo($dir);

		$l = new \OC_L10N('files');
		$maxUploadFileSize = \OCP\Util::maxUploadFilesize($dir, $storageInfo['free']);
		$maxHumanFileSize = \OCP\Util::humanFileSize($maxUploadFileSize);
		$maxHumanFileSize = $l->t('Upload (max. %s)', array($maxHumanFileSize));

		return array('uploadMaxFilesize' => $maxUploadFileSize,
					 'maxHumanFilesize'  => $maxHumanFileSize,
					 'freeSpace' => $storageInfo['free'],
					 'usedSpacePercent'  => (int)$storageInfo['relative']);
	}

	/**
	 * Determine icon for a given file
	 *
	 * @param \OCP\Files\FileInfo $file file info
	 * @param array $filesSharedByUser
	 * @return string icon URL
	 */
	public static function determineIcon($file, $filesSharedByUser = null) {
		if($file['type'] === 'dir') {
			$icon = \OC_Helper::mimetypeIcon('dir');
			if ($file->isShared()) {
				$icon = \OC_Helper::mimetypeIcon('dir-shared');
			} elseif (!empty($filesSharedByUser) && self::isSharedByUser($file, $filesSharedByUser)) {
				$icon = \OC_Helper::mimetypeIcon('dir-shared');
			} elseif ($file->isMounted()) {
				$icon = \OC_Helper::mimetypeIcon('dir-external');
			}
		}else{
			$icon = \OC_Helper::mimetypeIcon($file->getMimetype());
		}

		return substr($icon, 0, -3) . 'svg';
	}

	/**
	 * check if file is shared by the user
	 * @param \OCP\Files\FileInfo $file
	 * @param array $filesSharedByUser
	 * @return boolean
	 */
	private static function isSharedByUser($file, $filesSharedByUser) {
		foreach ($filesSharedByUser as $shared) {
			if ($file['path'] === 'files' . $shared['path']) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Comparator function to sort files alphabetically and have
	 * the directories appear first
	 *
	 * @param \OCP\Files\FileInfo $a file
	 * @param \OCP\Files\FileInfo $b file
	 * @return int -1 if $a must come before $b, 1 otherwise
	 */
	public static function compareFileNames($a, $b) {
		$aType = $a->getType();
		$bType = $b->getType();
		if ($aType === 'dir' and $bType !== 'dir') {
			return -1;
		} elseif ($aType !== 'dir' and $bType === 'dir') {
			return 1;
		} else {
			return strnatcasecmp($a->getName(), $b->getName());
		}
	}

	/**
	 * Comparator function to sort files by date
	 *
	 * @param \OCP\Files\FileInfo $a file
	 * @param \OCP\Files\FileInfo $b file
	 * @return int -1 if $a must come before $b, 1 otherwise
	 */
	public static function compareTimestamp($a, $b) {
		$aTime = $a->getMTime();
		$bTime = $b->getMTime();
		return $aTime - $bTime;
	}

	/**
	 * Comparator function to sort files by size
	 *
	 * @param \OCP\Files\FileInfo $a file
	 * @param \OCP\Files\FileInfo $b file
	 * @return int -1 if $a must come before $b, 1 otherwise
	 */
	public static function compareSize($a, $b) {
		$aSize = $a->getSize();
		$bSize = $b->getSize();
		return $aSize - $bSize;
	}

	/**
	 * Formats the file info to be returned as JSON to the client.
	 *
	 * @param \OCP\Files\FileInfo $file
	 * @param array $filesSharedByUser files shared by the user
	 * @return array formatted file info
	 */
	public static function formatFileInfo($file, $filesSharedByUser = null) {
		$entry = array();

		$entry['id'] = $file['fileid'];
		$entry['parentId'] = $file['parent'];
		$entry['date'] = \OCP\Util::formatDate($file['mtime']);
		$entry['mtime'] = $file['mtime'] * 1000;
		// only pick out the needed attributes
		$entry['icon'] = \OCA\Files\Helper::determineIcon($file, $filesSharedByUser);
		if (\OC::$server->getPreviewManager()->isMimeSupported($file['mimetype'])) {
			$entry['isPreviewAvailable'] = true;
		}
		$entry['name'] = $file['name'];
		$entry['permissions'] = $file['permissions'];
		$entry['mimetype'] = $file['mimetype'];
		$entry['size'] = $file['size'];
		$entry['type'] = $file['type'];
		$entry['etag'] = $file['etag'];
		if (isset($file['displayname_owner'])) {
			$entry['shareOwner'] = $file['displayname_owner'];
		}
		if (isset($file['is_share_mount_point'])) {
			$entry['isShareMountPoint'] = $file['is_share_mount_point'];
		}
		return $entry;
	}

	/**
	 * Format file info for JSON
	 * @param \OCP\Files\FileInfo[] $fileInfos file infos
	 */
	public static function formatFileInfos($fileInfos) {
		$files = array();
		$filesSharedByUser = \OCP\Share::getItemsShared('file');
		foreach ($fileInfos as $i) {
			$files[] = self::formatFileInfo($i, $filesSharedByUser);
		}

		return $files;
	}

	/**
	 * Retrieves the contents of the given directory and
	 * returns it as a sorted array of FileInfo.
	 *
	 * @param string $dir path to the directory
	 * @param string $sortAttribute attribute to sort on
	 * @param bool $sortDescending true for descending sort, false otherwise
	 * @return \OCP\Files\FileInfo[] files
	 */
	public static function getFiles($dir, $sortAttribute = 'name', $sortDescending = false) {
		$content = \OC\Files\Filesystem::getDirectoryContent($dir);

		return self::sortFiles($content, $sortAttribute, $sortDescending);
	}

	/**
	 * Sort the given file info array
	 *
	 * @param \OCP\Files\FileInfo[] $files files to sort
	 * @param string $sortAttribute attribute to sort on
	 * @param bool $sortDescending true for descending sort, false otherwise
	 * @return \OCP\Files\FileInfo[] sorted files
	 */
	public static function sortFiles($files, $sortAttribute = 'name', $sortDescending = false) {
		$sortFunc = 'compareFileNames';
		if ($sortAttribute === 'mtime') {
			$sortFunc = 'compareTimestamp';
		} else if ($sortAttribute === 'size') {
			$sortFunc = 'compareSize';
		}
		usort($files, array('\OCA\Files\Helper', $sortFunc));
		if ($sortDescending) {
			$files = array_reverse($files);
		}
		return $files;
	}
}
