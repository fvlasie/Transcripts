<?php

use Gibbon\Forms\Form;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\DataSet;
use Gibbon\Services\Format;
use Gibbon\Module\Transcripts\Domain\TranscriptGateway;
use Gibbon\Module\Transcripts\Domain\StudentProgramGateway;
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
            'error2' => __('The official transcript PDF could not be generated because the page background is missing. Upload it in Transcript Template.'),
            'error3' => __('The official transcript PDF could not be generated.'),
        ]);

        $transcriptGateway = $container->get(TranscriptGateway::class);
        $programGateway = $container->get(StudentProgramGateway::class);
        $transcriptService = new TranscriptService($transcriptGateway, $programGateway);
        $settingGateway = $container->get(\Gibbon\Domain\System\SettingGateway::class);
        $isOfficial = canGenerateOfficialTranscript($guid, $connection2, $settingGateway);
        $canEdit = $highestAction === 'Transcripts_all';

        $gibbonSchoolYearID = (int)$session->get('gibbonSchoolYearID');
        $gibbonPersonIDViewer = (int)$session->get('gibbonPersonID');
        $gibbonPersonID = '';

        if ($highestAction === 'Transcripts_all') {
            $gibbonPersonID = $_GET['gibbonPersonID'] ?? '';

            echo '<h2>';
            echo __('Choose Student');
            echo '</h2>';

            $form = Form::create('studentSelect', $session->get('absoluteURL').'/index.php', 'get');
            $form->setClass('noIntBorder w-full');
            $form->addHiddenValue('q', '/modules/'.$session->get('module').'/transcripts_view.php');

            $row = $form->addRow();
                $row->addLabel('gibbonPersonID', __('Student'))->description(__('Includes students from every year, alumni and leavers.'));
                $row->addSelect('gibbonPersonID')->fromArray(getTranscriptStudentOptions($pdo))->required()->placeholder()->selected($gibbonPersonID);

            $row = $form->addRow();
                $row->addSubmit(__('View Transcript'));

            echo $form->getOutput();

            echo '<div class="linkTop"><a href="'.$session->get('absoluteURL').'/index.php?q=/modules/'.$session->get('module').'/transcripts_cleanup.php">'.__('Grade Data Cleanup Report').'</a></div>';
        } elseif ($highestAction === 'Transcripts_myStudents') {
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
                    $row->addSubmit(__('View Transcript'));

                echo $form->getOutput();
            }
        } else {
            $gibbonPersonID = (string) $gibbonPersonIDViewer;
        }

        $denialReason = !empty($gibbonPersonID)
            ? getTranscriptAccessDenialReason($pdo, $highestAction, $gibbonPersonIDViewer, (int)$gibbonPersonID, $gibbonSchoolYearID)
            : null;

        if (empty($gibbonPersonID)) {
            if ($highestAction !== 'Transcripts_myStudents' || !empty($studentOptions ?? [])) {
                $page->addMessage(__('Select a student to view the transcript.'));
            }
        } elseif ($denialReason !== null) {
            $page->addError($denialReason);
        } else {
            $programs = $programGateway->getAllProgramsByPerson((int)$gibbonPersonID);
            $gibbonStudentProgramHistoryID = (int)($_GET['gibbonStudentProgramHistoryID'] ?? 0);
            $selectedProgram = resolveTranscriptsProgram($programs, $gibbonStudentProgramHistoryID);
            $gibbonStudentProgramHistoryID = (int)($selectedProgram['gibbonStudentProgramHistoryID'] ?? 0);

            if (count($programs) > 1) {
                echo '<h2>';
                echo __('Transcript Header');
                echo '</h2>';

                $programForm = Form::create('programSelect', $session->get('absoluteURL').'/index.php', 'get');
                $programForm->setClass('noIntBorder w-full');
                $programForm->addHiddenValue('q', '/modules/'.$session->get('module').'/transcripts_view.php');
                $programForm->addHiddenValue('gibbonPersonID', $gibbonPersonID);

                $row = $programForm->addRow();
                    $row->addLabel('gibbonStudentProgramHistoryID', __('Program'))->description(__('Sets the program shown in the transcript header. Every graded course is listed regardless of program.'));
                    $row->addSelect('gibbonStudentProgramHistoryID')
                        ->fromArray(getTranscriptsProgramOptions($programs))
                        ->required()
                        ->selected($gibbonStudentProgramHistoryID);

                $row = $programForm->addRow();
                    $row->addSubmit(__('Update Header'));

                echo $programForm->getOutput();
            }

            $transcriptData = $transcriptService->generateStudentTranscript((int)$gibbonPersonID, $selectedProgram);

            $printUrl = $session->get('absoluteURL').'/modules/'.$session->get('module').'/transcript_print.php?gibbonPersonID='.$gibbonPersonID;
            if ($gibbonStudentProgramHistoryID > 0) {
                $printUrl .= '&gibbonStudentProgramHistoryID='.$gibbonStudentProgramHistoryID;
            }

            echo renderTranscriptSummary($transcriptData, $selectedProgram, $printUrl, $isOfficial);

            $editContext = [
                'ajaxURL' => $session->get('absoluteURL').'/modules/'.$session->get('module').'/transcripts_gradeAjax.php'
                    .($gibbonStudentProgramHistoryID > 0 ? '?gibbonStudentProgramHistoryID='.$gibbonStudentProgramHistoryID : ''),
                'csrftoken' => $session->get('csrftoken'),
                'gibbonPersonID' => (int)$gibbonPersonID,
            ];
            $scaleCache = [];

            if ($canEdit) {
                echo renderTranscriptInlineEditScript();
                echo '<div class="transcriptsEditable">';
            }

            $records = $transcriptData['records'] ?? [];
            $table = DataTable::create('academicRecord');
            $table->setTitle(__('Academic Record'));
            if ($canEdit) {
                $table->setDescription(__('Grades are saved the same way as Write Reports. Credits are shared with Courses and Classes and apply to every year of the course; external codes are edited in Courses and Classes > Manage All Courses.'));
            }

            $table->addColumn('schoolYear', __('Year'));
            $table->addColumn('term', __('Term'));
            $table->addColumn('courseCode', __('Course Code'));
            $table->addColumn('externalCourseCode', __('External Course Code'))
                ->format(function ($row) {
                    return htmlspecialchars($row['externalCourseCode'] ?? '');
                });
            $table->addColumn('courseName', __('Course Name'));
            $table->addColumn('learningArea', __('Concentration'))
                ->format(function ($row) {
                    return htmlspecialchars($row['learningArea'] ?? '');
                });
            $table->addColumn('courseLevel', __('Level'));
            $table->addColumn('modeOfInstruction', __('Mode'));
            $table->addColumn('credits', __('Credits'))
                ->format(function ($row) use ($canEdit, $editContext) {
                    return $canEdit
                        ? renderTranscriptCatalogInput($row, 'credits', $editContext)
                        : number_format((float)$row['credits'], 2);
                })
                ->addClass('text-right');
            $table->addColumn('letterGrade', __('Grade'))
                ->format(function ($row) use ($canEdit, $editContext, $transcriptGateway, &$scaleCache) {
                    if (!$canEdit) {
                        return htmlspecialchars($row['letterGrade'] ?? '-');
                    }
                    if (!empty($row['isPassFail'])) {
                        return htmlspecialchars($row['letterGrade'] ?? '-')
                            .'<div class="text-xxs text-gray-600 mt-1">'.__('Pass/Fail: a pass counts as A, a fail as F. Edit in Write Reports.').'</div>'
                            .renderTranscriptLastChanged($row);
                    }

                    $choices = buildTranscriptGradeChoices($transcriptGateway, [[
                        'gibbonReportingCriteriaID' => $row['gibbonReportingCriteriaID'],
                        'gibbonScaleID' => $row['gibbonScaleID'],
                        'termName' => $row['termName'],
                        'cycleName' => $row['cycleName'],
                    ]], $scaleCache);

                    $html = renderTranscriptGradeCell($row, $choices, $editContext);
                    if (!empty($row['hiddenDuplicates'])) {
                        $html .= '<div class="text-xxs text-orange-700">'.__('Other grades exist for this term; see the cleanup report.').'</div>';
                    }

                    return $html;
                });
            $table->addColumn('gpaPoints', __('GPA Points'))
                ->format(function ($row) {
                    return $row['gpaPoints'] !== null ? number_format($row['gpaPoints'], 1) : '-';
                })
                ->addClass('text-right');

            if (!empty($records)) {
                echo $table->render(new DataSet($records));
            } else {
                echo $page->getBlankSlate();
            }

            if ($canEdit) {
                $ungraded = $transcriptGateway->getStudentUngradedClasses((int)$gibbonPersonID);

                if (!empty($ungraded)) {
                    $ungradedTable = DataTable::create('ungradedClasses');
                    $ungradedTable->setTitle(__('Ungraded Enrolments'));
                    $ungradedTable->setDescription(__('Classes this student is enrolled in without a grade. They are not on the transcript until a grade is entered.'));

                    $ungradedTable->addColumn('schoolYearName', __('Year'));
                    $ungradedTable->addColumn('courseCode', __('Course Code'))
                        ->format(function ($row) {
                            return htmlspecialchars($row['courseCode'].'.'.$row['className']);
                        });
                    $ungradedTable->addColumn('courseName', __('Course Name'));
                    $ungradedTable->addColumn('grade', __('Grade'))
                        ->format(function ($row) use ($editContext, $transcriptGateway, &$scaleCache) {
                            $criteriaRows = $transcriptGateway->getGradeCriteriaForClass((int)$row['gibbonCourseClassID']);

                            if (!empty($criteriaRows)) {
                                $choices = buildTranscriptGradeChoices($transcriptGateway, $criteriaRows, $scaleCache);

                                return renderTranscriptGradeCell($row, $choices, $editContext, true);
                            }

                            $terms = $transcriptGateway->getTermsBySchoolYear((int)$row['gibbonSchoolYearID']);
                            if (empty($terms)) {
                                return '<span class="text-gray-600">'.__('This school year has no terms, so grading cannot be set up.').'</span>';
                            }

                            $plannerTermID = $transcriptGateway->getPlannerTermIDForClass((int)$row['gibbonCourseClassID']);
                            $selectID = 'setupTerm'.(int)$row['gibbonCourseClassID'];
                            $vals = [
                                'action' => 'setupTerm',
                                'csrftoken' => $editContext['csrftoken'],
                                'gibbonPersonID' => $editContext['gibbonPersonID'],
                                'gibbonCourseClassID' => (int)$row['gibbonCourseClassID'],
                            ];

                            $html = '<div class="transcriptGradeCell flex items-center gap-2">';
                            $html .= '<select id="'.$selectID.'" name="gibbonSchoolYearTermID" aria-label="'.__('Term').'">';
                            foreach ($terms as $termID => $termName) {
                                $html .= '<option value="'.(int)$termID.'"'.((int)$termID === $plannerTermID ? ' selected' : '').'>'.htmlspecialchars($termName).'</option>';
                            }
                            $html .= '</select>';
                            $html .= '<button type="button" class="button"'
                                .' hx-post="'.htmlspecialchars($editContext['ajaxURL']).'" hx-include="#'.$selectID.'" hx-target="closest .transcriptGradeCell" hx-swap="outerHTML"'
                                .' hx-vals="'.htmlspecialchars(json_encode($vals), ENT_QUOTES).'"'
                                .' hx-confirm="'.htmlspecialchars(sprintf(__('This adds a grade criterion for %1$s to the selected term\'s reporting cycle in Reports (creating the cycle if needed), so grades can be entered here and in Write Reports. Continue?'), $row['courseCode'])).'">'
                                .__('Set up grading for this term').'</button>';
                            $html .= '</div>';

                            return $html;
                        });

                    echo $ungradedTable->render(new DataSet($ungraded));
                }

                echo '</div>';
            }
        }
    }
}
