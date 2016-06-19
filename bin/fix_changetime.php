<?php 
/**
 * Fix modification times based on timestamps. Run from within DokuWiki installation directory.
 * @Author: dreamlusion <http://dreamlusion.eucoding.com>
  * last modified: 2008-09-05 4:15:00
 */
function WalkDirectory($parentDirectory) {
	global $_weeds;
 
	foreach(array_diff(scandir($parentDirectory), $_weeds) as $directory)
	{
		$path = $parentDirectory . '/'. $directory;
 
		if(is_dir($path)) 
		{
			WalkDirectory($path);
		}
		else 
		{
			// Calculate changes file path.
			$path_parts = pathinfo($path);
 
			// Remove pages path.
			global $_pagesPath;
			$relativePath = substr($path_parts['dirname'], strlen($_pagesPath), strlen($path_parts['dirname']));
 
			// Add <filename>.changes
			$filename = $path_parts['filename']; // Requires PHP 5.2.0 (http://gr2.php.net/manual/en/function.pathinfo.php)
			$relativePath .= '/' . $filename . '.' . 'changes';
 
			global $_metaPath;
			$changelog = $_metaPath . '/' . $relativePath;
 
			if (is_file($changelog))
			{
				$handle = @fopen($changelog, "r");
				if ($handle)
				{
					while (!feof($handle))
					{
						$buffer = fgets($handle);
						preg_match('/(?<timestamp>\d+)/', $buffer, $matches);

						if (!empty($matches['timestamp']))
							$timestamp = $matches['timestamp'];
					}

					fclose($handle);
				}

				if (filemtime($path) == $timestamp)
				{
					continue;
				}
 
				// At this point we have our timestamp.
				if (touch($path, $timestamp))
				{
					echo 'Updating ' . $path . "\n";
					//echo 'Old modification time: ' . filemtime($path) . "\n";

					// In my host, although the timestamp had changed successfully (checked manually), running filemtime($path) at this point 
					// did not return the correct timestamp, so use I use $timestamp instead of filemtime($path) to avoid confusing the user.
					//echo 'New modification time: ' . $timestamp . "\n";
				}
				else
				{
					fwrite(STDERR, 'Could not change modification time for page ' . $filename . "\n");
				}
			}
			else
			{
				fwrite(STDERR, 'Changelog not found: ' . $changelog . "\n");
			}
		}
	} 
}
 
$_weeds = array('.', '..');
$_pagesPath = getcwd() . '/data/pages';
$_metaPath = getcwd() . '/data/meta';
 
WalkDirectory($_pagesPath);
 
?>
