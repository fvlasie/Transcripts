<?php

use Gibbon\Forms\Form;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\DataSet;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Services\Format;
use Gibbon\Module\Transcripts\Domain\TranscriptGateway;
use Gibbon\Module\Transcripts\Domain\StudentProgramGateway;
use Gibbon\Module\Transcripts\Domain\CourseProgramGateway;
use Gibbon\Module\Transcripts\Services\TranscriptService;

require_once __DIR__.'/moduleFunctions.php';
checkAndMigrateTranscriptsSchema($pdo);

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/transcripts_view.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $highestAction = getTranscriptViewAction($guid, $connection2);

    if (empty($highestAction)) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        $page->breadcrumbs->add(__('Transcripts'));

        $page->return->addReturns([
            'error2' => __('The official transcript PDF could not be generated. Upload page backgrounds in Manage Transcript Template.'),
            'error3' => __('The academic record could not be saved.'),
            'success1' => __('The academic record was updated successfully.'),
            'success2' => __('The academic record was added successfully.'),
        ]);

        $transcriptGateway = $container->get(TranscriptGateway::class);
        $programGateway = $container->get(StudentProgramGateway::class);
        $transcriptService = new TranscriptService($transcriptGateway, $programGateway, $container->get(CourseProgramGateway::class));
        $settingGateway = $container->get(\Gibbon\Domain\System\SettingGateway::class);
        $isOfficial = canGenerateOfficialTranscript($guid, $connection2, $settingGateway);

        $gibbonSchoolYearID = (int)$session->get('gibbonSchoolYearID');
        $gibbonPersonIDViewer = (int)$session->get('gibbonPersonID');
        $gibbonPersonID = '';

        if ($highestAction === 'Generate Transcripts_all') {
            $gibbonPersonID = $_GET['gibbonPersonID'] ?? '';

            echo '<h2>';
            echo __('Choose Student');
            echo '</h2>';

            $form = Form::create('studentSelect', $session->get('absoluteURL').'/index.php', 'get');
            $form->setFactory(DatabaseFormFactory::create($pdo));
            $form->setClass('noIntBorder w-full');
            $form->addHiddenValue('q', '/modules/'.$session->get('module').'/transcripts_view.php');

            $row = $form->addRow();
                $row->addLabel('gibbonPersonID', __('Student'));
                $row->addSelectStudent('gibbonPersonID', $gibbonSchoolYearID)->required()->placeholder()->selected($gibbonPersonID);

            $row = $form->addRow();
                $row->addSearchSubmit($session, __('Clear'));

            echo $form->getOutput();
        } elseif ($highestAction === 'Generate Transcripts_myStudents') {
            $studentOptions = getTeacherStudentOptions($pdo, $gibbonSchoolYearID, $gibbonPersonIDViewer);
            $gibbonPersonID = $_GET['gibbonPersonID'] ?? '';

            if (empty($studentOptions)) {
                echo $page->getBlankSlate();
            } elseif (count($studentOptions) === 1) {
                $gibbonPersonID = (string) key($studentOptions);
            } else {
                echo '<h2>';
                echo __('Choose Student');
                echo '</h2>';

                $form = Form::create('studentSelect', $session->get('absoluteURL').'/index.php', 'get');
                $form->setClass('noIntBorder w-full');
                $form->addHiddenValue('q', '/modules/'.$session->get('module').'/transcripts_view.php');

                $row = $form->addRow();
                    $row->addLabel('gibbonPersonID', __('Student'));
                    $row->addSelect('gibbonPersonID')->fromArray($studentOptions)->required()->placeholder()->selected($gibbonPersonID);

                $row = $form->addRow();
                    $row->addSearchSubmit($session, __('Clear'));

                echo $form->getOutput();
            }
        } else {
            $gibbonPersonID = (string) $gibbonPersonIDViewer;
        }

        if (empty($gibbonPersonID)) {
            if ($highestAction !== 'Generate Transcripts_myStudents' || !empty($studentOptions ?? [])) {
                $page->addMessage(__('Select a student to view his transcript.'));
            }
        } elseif (!canViewStudentTranscript($pdo, $highestAction, $gibbonPersonIDViewer, (int)$gibbonPersonID, $gibbonSchoolYearID)) {
            $page->addError(__('The selected record does not exist, or you do not have access to it.'));
        } else {
            $programs = $programGateway->getAllProgramsByPerson((int)$gibbonPersonID);
            $gibbonStudentProgramHistoryID = (int)($_GET['gibbonStudentProgramHistoryID'] ?? 0);
            $selectedProgram = resolveTranscriptsProgram($programs, $gibbonStudentProgramHistoryID);
            $gibbonStudentProgramHistoryID = (int)($selectedProgram['gibbonStudentProgramHistoryID'] ?? 0);

            if (count($programs) > 1) {
                echo '<h2>';
                echo __('Choose Program');
                echo '</h2>';

                $programForm = Form::create('programSelect', $session->get('absoluteURL').'/index.php', 'get');
                $programForm->setClass('noIntBorder w-full');
                $programForm->addHiddenValue('q', '/modules/'.$session->get('module').'/transcripts_view.php');
                $programForm->addHiddenValue('gibbonPersonID', $gibbonPersonID);

                $row = $programForm->addRow();
                    $row->addLabel('gibbonStudentProgramHistoryID', __('Program'));
                    $row->addSelect('gibbonStudentProgramHistoryID')
                        ->fromArray(getTranscriptsProgramOptions($programs))
                        ->required()
                        ->selected($gibbonStudentProgramHistoryID);

                $row = $programForm->addRow();
                    $row->addSearchSubmit($session, __('Clear'), ['gibbonPersonID']);

                echo $programForm->getOutput();
            }

            $transcriptData = $transcriptService->generateStudentTranscript((int)$gibbonPersonID, $selectedProgram);

            $printUrl = $session->get('absoluteURL').'/modules/'.$session->get('module').'/transcript_print.php?gibbonPersonID='.$gibbonPersonID;
            if ($gibbonStudentProgramHistoryID > 0) {
                $printUrl .= '&gibbonStudentProgramHistoryID='.$gibbonStudentProgramHistoryID;
            }

            echo '<div class="linkTop">';
            if (!empty($selectedProgram)) {
                echo '<strong>'.__('Program').':</strong> '.htmlspecialchars(formatTranscriptsProgramLabel($selectedProgram)).' | ';
            }
            echo '<strong>'.__('Cumulative GPA').':</strong> '.renderGpaBadge($transcriptData['cumulativeGPA']);
            echo ' | <strong>'.__('Total Credits Earned').':</strong> '.$transcriptData['totalCredits'];
            echo ' | <a href="'.$printUrl.'" target="_blank">'.($isOfficial ? __('Official PDF') : __('Unofficial PDF')).'</a>';
            echo '</div>';

            $records = $transcriptData['records'] ?? [];
            $table = DataTable::create('academicRecord');
            $table->setTitle(__('Academic Record'));

            $table->addColumn('schoolYear', __('Year'));
            $table->addColumn('term', __('Term'));
            $table->addColumn('courseCode', __('Course Code'));
            $table->addColumn('externalCourseCode', __('External Course Code'))
                ->format(function ($row) {
                    return $row['externalCourseCode'] ?? '';
                });
            $table->addColumn('courseName', __('Course Name'));
            $table->addColumn('courseLevel', __('Level'));
            $table->addColumn('modeOfInstruction', __('Mode'));
            $table->addColumn('credits', __('Credits'))->format(Format::using('number', ['credits', 2]))->addClass('text-right');
            $table->addColumn('letterGrade', __('Grade'))
                ->format(function ($row) {
                    return $row['letterGrade'] ?? '-';
                })
                ->addClass('text-right');
            $table->addColumn('gpaPoints', __('GPA Points'))
                ->format(function ($row) {
                    return $row['gpaPoints'] !== null ? number_format($row['gpaPoints'], 1) : '-';
                })
                ->addClass('text-right');

            if ($highestAction === 'Generate Transcripts_all') {
                $actionColumn = $table->addActionColumn()
                    ->addParam('gibbonPersonID', $gibbonPersonID)
                    ->addParam('gibbonCourseClassID')
                    ->addParam('gibbonSchoolYearTermID')
                    ->addParam('gibbonReportingValueID')
                    ->addParam('gibbonReportingCycleID');

                if ($gibbonStudentProgramHistoryID > 0) {
                    $actionColumn->addParam('gibbonStudentProgramHistoryID', $gibbonStudentProgramHistoryID);
                }

                $actionColumn->format(function ($row, $actions) {
                        $actions->addAction('edit', __('Edit'))
                            ->setURL('/fullscreen.php')
                            ->addParam('q', '/modules/Transcripts/transcripts_edit.php')
                            ->directLink(true)
                            ->modalWindow();
                    });
            }

            if (!empty($records)) {
                echo $table->render(new DataSet($records));
            } else {
                echo $page->getBlankSlate();
            }
        }
    }
}
