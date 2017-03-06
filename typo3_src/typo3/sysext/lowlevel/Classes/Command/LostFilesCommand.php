<?php
declare(strict_types=1);
namespace TYPO3\CMS\Lowlevel\Command;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\ReferenceIndex;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Finds files within uploads/ which are not needed anymore
 */
class LostFilesCommand extends Command
{

    /**
     * Configure the command by defining the name, options and arguments
     */
    public function configure()
    {
        $this
            ->setDescription('Looking for files in the uploads/ folder which does not have a reference in TYPO3 managed records.')
            ->setHelp('
Assumptions:
- a perfect integrity of the reference index table (always update the reference index table before using this tool!)
- that all contents in the uploads folder are files attached to TCA records and exclusively managed by DataHandler through "group" type fields
- index.html, .htaccess files and RTEmagic* image files (ignored)
- Files found in deleted records are included (otherwise you would see a false list of lost files)

The assumptions are not requirements by the TYPO3 API but reflects the de facto implementation of most TYPO3 installations and therefore a practical approach to cleaning up the uploads/ folder.
Therefore, if all "group" type fields in TCA and flexforms are positioned inside the uploads/ folder and if no files inside are managed manually it should be safe to clean out files with no relations found in the system.
Under such circumstances there should theoretically be no lost files in the uploads/ folder since DataHandler should have managed relations automatically including adding and deleting files.
However, there is at least one reason known to why files might be found lost and that is when FlexForms are used. In such a case a change of/in the Data Structure XML (or the ability of the system to find the Data Structure definition!) used for the flexform could leave lost files behind. This is not unlikely to happen when records are deleted. More details can be found in a note to the function FlexFormTools->getDataStructureIdentifier()
Another scenario could of course be de-installation of extensions which managed files in the uploads/ folders.

If the option "--dry-run" is not set, the files are then deleted automatically.
Warning: First, make sure those files are not used somewhere TYPO3 does not know about! See the assumptions above.

If you want to get more detailed information, use the --verbose option.')
            ->addOption(
                'exclude',
                null,
                InputOption::VALUE_REQUIRED,
                'Comma-separated list of paths that should be excluded, e.g. "uploads/pics,uploads/media"'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'If this option is set, the files will not actually be deleted, but just the output which files would be deleted are shown'
            )
            ->addOption(
                'update-refindex',
                null,
                InputOption::VALUE_NONE,
                'Setting this option automatically updates the reference index and does not ask on command line. Alternatively, use -n to avoid the interactive mode'
            );
    }

    /**
     * Executes the command to
     * - optionally update the reference index (to have clean data)
     * - find files within uploads/* which are not connected to the reference index
     * - remove these files if --dry-run is not set
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Make sure the _cli_ user is loaded
        Bootstrap::getInstance()->initializeBackendAuthentication();

        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());

        $dryRun = $input->hasOption('dry-run') && $input->getOption('dry-run') != false ? true : false;

        $this->updateReferenceIndex($input, $io);

        // Find the lost files
        if ($input->hasOption('exclude') && !empty($input->getOption('exclude'))) {
            $excludedPaths = GeneralUtility::trimExplode(',', $input->getOption('exclude'), true);
        } else {
            $excludedPaths = [];
        }
        $lostFiles = $this->findLostFiles($excludedPaths);

        if (count($lostFiles)) {
            if (!$io->isQuiet()) {
                $io->note('Found ' . count($lostFiles) . ' lost files, ready to be deleted.');
                if ($io->isVerbose()) {
                    $io->listing($lostFiles);
                }
            }

            // Delete them
            $this->deleteLostFiles($lostFiles, $dryRun, $io);

            $io->success('Deleted ' . count($lostFiles) . ' lost files.');
        } else {
            $io->success('Nothing to do, no lost files found');
        }
    }

    /**
     * Function to update the reference index
     * - if the option --update-refindex is set, do it
     * - otherwise, if in interactive mode (not having -n set), ask the user
     * - otherwise assume everything is fine
     *
     * @param InputInterface $input holds information about entered parameters
     * @param SymfonyStyle $io necessary for outputting information
     * @return void
     */
    protected function updateReferenceIndex(InputInterface $input, SymfonyStyle $io)
    {
        // Check for reference index to update
        $io->note('Finding lost files managed by TYPO3 requires a clean reference index (sys_refindex)');
        $updateReferenceIndex = false;
        if ($input->hasOption('update-refindex') && $input->getOption('update-refindex')) {
            $updateReferenceIndex = true;
        } elseif ($input->isInteractive()) {
            $updateReferenceIndex = $io->confirm('Should the reference index be updated right now?', false);
        }

        // Update the reference index
        if ($updateReferenceIndex) {
            $referenceIndex = GeneralUtility::makeInstance(ReferenceIndex::class);
            $referenceIndex->updateIndex(false, !$io->isQuiet());
        } else {
            $io->writeln('Reference index is assumed to be up to date, continuing.');
        }
    }

    /**
     * Find lost files in uploads/ folder
     *
     * @param array $excludedPaths list of paths to be excluded, can be uploads/pics/
     * @return array an array of files (relative to PATH_site) that are not connected
     */
    protected function findLostFiles($excludedPaths = []): array
    {
        $lostFiles = [];

        // Get all files
        $files = [];
        $files = GeneralUtility::getAllFilesAndFoldersInPath($files, PATH_site . 'uploads/');
        $files = GeneralUtility::removePrefixPathFromList($files, PATH_site);

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_refindex');

        // Traverse files and for each, look up if its found in the reference index.
        foreach ($files as $key => $value) {

            // First, allow "index.html", ".htaccess" files since they are often used for good reasons
            if (substr($value, -11) === '/index.html' || substr($value, -10) === '/.htaccess') {
                continue;
            }

            // If the file is a RTEmagic-image name and if so, we allow it
            if (preg_match('/^RTEmagic[P|C]_/', basename($value))) {
                continue;
            }

            $fileIsInExcludedPath = false;
            foreach ($excludedPaths as $exclPath) {
                if (GeneralUtility::isFirstPartOfStr($value, $exclPath)) {
                    $fileIsInExcludedPath = true;
                    break;
                }
            }

            if ($fileIsInExcludedPath) {
                continue;
            }

            // Looking for a reference from a field which is NOT a soft reference (thus, only fields with a proper TCA/Flexform configuration)
            $result = $queryBuilder
                ->select('hash')
                ->from('sys_refindex')
                ->where(
                    $queryBuilder->expr()->eq(
                        'ref_table',
                        $queryBuilder->createNamedParameter('_FILE', \PDO::PARAM_STR)
                    ),
                    $queryBuilder->expr()->eq(
                        'ref_string',
                        $queryBuilder->createNamedParameter($value, \PDO::PARAM_STR)
                    ),
                    $queryBuilder->expr()->eq(
                        'softref_key',
                        $queryBuilder->createNamedParameter('', \PDO::PARAM_STR)
                    )
                )
                ->orderBy('sorting', 'DESC')
                ->execute();

            // We conclude that the file is lost
            if ($result->rowCount() === 0) {
                $lostFiles[] = $value;
            }
        }

        return $lostFiles;
    }

    /**
     * Removes given files from the uploads/ folder
     *
     * @param array $lostFiles Contains the lost files found
     * @param bool $dryRun if set, the files are just displayed, but not deleted
     * @param SymfonyStyle $io the IO object for output
     * @return void
     */
    protected function deleteLostFiles(array $lostFiles, bool $dryRun, SymfonyStyle $io)
    {
        foreach ($lostFiles as $lostFile) {
            $absoluteFileName = GeneralUtility::getFileAbsFileName($lostFile);
            if ($io->isVeryVerbose()) {
                $io->writeln('Deleting file "' . $absoluteFileName . '"');
            }
            if (!$dryRun) {
                if ($absoluteFileName && @is_file($absoluteFileName)) {
                    unlink($absoluteFileName);
                    if (!$io->isQuiet()) {
                        $io->writeln('Permanently deleted file record "' . $absoluteFileName . '".');
                    }
                } else {
                    $io->error('File "' . $absoluteFileName . '" was not found!');
                }
            }
        }
    }
}
