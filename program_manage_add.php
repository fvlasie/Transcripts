<?php

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Services\Format;
use Gibbon\Module\Transcripts\Domain\StudentProgramGateway;

require_once __DIR__.'/moduleFunctions.php';
checkAndMigrateTranscriptsSchema($pdo);

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/program_manage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->return->addReturns([
        'error7' => __('Student is required.'),
        'error8' => __('Program Type is required.'),
        'error9' => __('Start Date is required.'),
        'error10' => __('Status is required.'),
        'error1' => __('The program record could not be saved. Check the student, program, start date, and status.'),
    ]);
    $filterGibbonPersonID = $_GET['gibbonPersonID'] ?? '';
    $suggested = $container->get(StudentProgramGateway::class)->suggestProgramDates((int)$filterGibbonPersonID);
    $backQuery = 'program_manage.php';
    if (!empty($filterGibbonPersonID)) {
        $backQuery .= '&gibbonPersonID='.$filterGibbonPersonID;
    }

    if (($_GET['cohort'] ?? '') === '1') {
        $gibbonSchoolYearID = (int) ($_GET['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID'));
        $gibbonYearGroupID = (int) ($_GET['gibbonYearGroupID'] ?? 0);
        $gender = in_array($_GET['gender'] ?? '', ['M', 'F', 'Other', 'Unspecified'], true) ? $_GET['gender'] : '';
        $programGateway = $container->get(StudentProgramGateway::class);

        $page->breadcrumbs
            ->add(__('Program Management'), $backQuery)
            ->add(__('Add Cohort'));

        echo '<h2>'.__('Filter').'</h2>';
        $filterForm = Form::create('cohortFilter', $session->get('absoluteURL').'/index.php', 'get');
        $filterForm->setFactory(DatabaseFormFactory::create($pdo));
        $filterForm->setClass('noIntBorder w-full');
        $filterForm->addHiddenValue('q', '/modules/'.$session->get('module').'/program_manage_add.php');
        $filterForm->addHiddenValue('cohort', '1');
        $row = $filterForm->addRow();
            $row->addLabel('gibbonSchoolYearID', __('School Year'));
            $row->addSelectSchoolYear('gibbonSchoolYearID', 'All')->required()->selected($gibbonSchoolYearID);
        $row = $filterForm->addRow();
            $row->addLabel('gibbonYearGroupID', __('Year Group'));
            $row->addSelect('gibbonYearGroupID')
                ->fromQuery($pdo, 'SELECT gibbonYearGroupID AS value, name FROM gibbonYearGroup ORDER BY sequenceNumber')
                ->placeholder(__('All Year Groups'))
                ->selected($gibbonYearGroupID);
        $row = $filterForm->addRow();
            $row->addLabel('gender', __('Gender'));
            $row->addSelect('gender')->fromArray([
                'M' => __('Male'),
                'F' => __('Female'),
                'Other' => __('Other'),
                'Unspecified' => __('Unspecified'),
            ])->placeholder(__('All'))->selected($gender);
        $row = $filterForm->addRow();
            $row->addSearchSubmit($session);
        echo $filterForm->getOutput();

        $students = $programGateway->selectCohortStudents($gibbonSchoolYearID, $gibbonYearGroupID, $gender);
        $firstDay = $pdo->selectOne(
            'SELECT firstDay FROM gibbonSchoolYear WHERE gibbonSchoolYearID = :gibbonSchoolYearID',
            ['gibbonSchoolYearID' => $gibbonSchoolYearID]
        );

        echo '<h2>'.__('Add Students').'</h2>';
        if (empty($students)) {
            echo Format::alert(__('There are no students enrolled in this school year.'), 'message');
            return;
        }

        $options = [];
        foreach ($students as $student) {
            $group = $student['yearGroup'] !== '' ? $student['yearGroup'] : __('No Year Group');
            $label = Format::name('', $student['preferredName'], $student['surname'], 'Student', true).' ('.$student['username'].')';
            if (!empty($student['activeProgram'])) {
                $label .= ' — '.$student['activeProgram'];
            }
            $options[$group][(int) $student['gibbonPersonID']] = $label;
        }

        $form = Form::create('programCohort', $session->get('absoluteURL').'/modules/'.$session->get('module').'/program_manageProcess.php');
        $form->setFactory(DatabaseFormFactory::create($pdo));
        $form->addHiddenValue('address', $session->get('address'));
        $form->addHiddenValue('intent', 'addCohort');
        $form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);
        $form->addHiddenValue('gibbonYearGroupID', $gibbonYearGroupID);
        $form->addHiddenValue('gender', $gender);

        $row = $form->addRow();
            $row->addLabel('gibbonPersonID', __('Students'))->description(__('Students who already have an active program are marked with that program.'));
            $row->addSelect('gibbonPersonID')->fromArray($options)->selectMultiple()->setAttribute('size', '12')->required();

        $row = $form->addRow();
            $row->addLabel('programType', __('Program Type'));
            $row->addSelect('programType')->fromArray(getTranscriptsProgramTypes($pdo))->required()->placeholder();

        $row = $form->addRow();
            $row->addLabel('startDate', __('Start Date'))->description(__('The first day of the selected school year. You can change it.'));
            $startDate = $row->addDate('startDate')->required();
            if (!empty($firstDay) && $firstDay !== '0000-00-00') {
                $startDate->setValue(Format::date($firstDay));
            }

        $row = $form->addRow();
            $row->addLabel('status', __('Status'));
            $row->addSelect('status')->fromArray(getTranscriptsProgramStatuses())->required()->selected('Active');

        $row = $form->addRow();
            $row->addLabel('notes', __('Notes'));
            $row->addTextArea('notes')->setRows(3);

        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit();

        echo $form->getOutput();
        return;
    }

    $page->breadcrumbs
        ->add(__('Program Management'), $backQuery)
        ->add(__('Add'));

    $form = Form::create('programAdd', $session->get('absoluteURL').'/modules/'.$session->get('module').'/program_manageProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('filterGibbonPersonID', $filterGibbonPersonID);

    $row = $form->addRow();
        $row->addLabel('gibbonPersonID', __('Student'))->description(__('Required'));
        $row->addSelectStudent('gibbonPersonID', $session->get('gibbonSchoolYearID'), ['allStudents' => true])->required()->placeholder()->selected($filterGibbonPersonID);

    $row = $form->addRow();
        $row->addLabel('programType', __('Program Type'))->description(__('Choose a program. Nothing is selected until you choose one.'));
        $row->addSelect('programType')->fromArray(getTranscriptsProgramTypes($pdo))->required()->placeholder();

    $row = $form->addRow();
        $row->addLabel('startDate', __('Start Date'))->description($suggested['startSource'] !== '' ? $suggested['startSource'] : __('Required'));
        $startDate = $row->addDate('startDate')->required();
        if (!empty($suggested['startDate'])) {
            $startDate->setValue(Format::date($suggested['startDate']));
        }

    $row = $form->addRow();
        $row->addLabel('switchDate', __('Switch Date'));
        $row->addDate('switchDate');

    $row = $form->addRow();
        $row->addLabel('graduationDate', __('Graduation Date'))->description($suggested['graduationSource']);
        $graduationDate = $row->addDate('graduationDate');
        if (!empty($suggested['graduationDate'])) {
            $graduationDate->setValue(Format::date($suggested['graduationDate']));
        }

    $row = $form->addRow();
        $row->addLabel('status', __('Status'));
        $row->addSelect('status')->fromArray(getTranscriptsProgramStatuses())->required()->selected('Active');

    $row = $form->addRow();
        $row->addLabel('notes', __('Notes'));
        $row->addTextArea('notes')->setRows(3);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();

    $reloadURL = $session->get('absoluteURL').'/index.php?q=/modules/'.$session->get('module').'/program_manage_add.php';
    echo '<script>
    var student = document.querySelector("form#programAdd select[name=gibbonPersonID]");
    if (student) {
        student.addEventListener("change", function () {
            if (!this.value) return;
            window.location = '.json_encode($reloadURL).' + "&gibbonPersonID=" + encodeURIComponent(this.value);
        });
    }
    </script>';
}
