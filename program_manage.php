<?php
use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Module\Transcripts\Gateway\StudentProgramGateway;

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/program_manage.php') == false) {
    echo "<div class='alert alert-danger' role='alert'>" . __('You do not have access to this action.') . "</div>";
} else {
    echo "<h2>" . __('Manage Student Programs & Program Dates') . "</h2>";

    $page->breadcrumbs->add(__('Program Dates Management'));

    $programGateway = new StudentProgramGateway($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_program'])) {
        $data = [
            'gibbonPersonID' => (int)$_POST['gibbonPersonID'],
            'programType' => $_POST['programType'],
            'concentration' => $_POST['concentration'],
            'studentLevel' => $_POST['studentLevel'],
            'startDate' => $_POST['startDate'],
            'switchDate' => !empty($_POST['switchDate']) ? $_POST['switchDate'] : null,
            'graduationDate' => !empty($_POST['graduationDate']) ? $_POST['graduationDate'] : null,
            'status' => $_POST['status'],
            'notes' => $_POST['notes']
        ];

        $programGateway->addProgramHistory($data);
        echo "<div class='alert alert-success' role='alert'>" . __('Student program dates record saved successfully.') . "</div>";
    }

    // Create form using Gibbon Form API
    $form = Form::create('programManage', $session->get('absoluteURL').'/modules/'.$session->get('module').'/program_manage.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setClass('w-full');
    $form->addHiddenValue('save_program', '1');

    $row = $form->addRow();
        $row->addLabel('gibbonPersonID', __('Student'))->description(__('Required'));
        $row->addSelectStudent('gibbonPersonID', $session->get('gibbonSchoolYearID'))->required()->placeholder();

    $row = $form->addRow();
        $row->addLabel('programType', __('Program Type'));
        $row->addSelect('programType')
            ->fromArray(['MTS' => 'MTS', 'BTh' => 'BTh', 'Iconography' => 'Iconography', 'Iconology' => 'Iconology', 'Gap-Year' => 'Gap-Year', 'Non-Degree' => 'Non-Degree'])
            ->required();

    $row = $form->addRow();
        $row->addLabel('concentration', __('Concentration'));
        $row->addSelect('concentration')
            ->fromArray(['Biblical' => 'Biblical', 'Professional' => 'Professional', 'General' => 'General', 'Certificate' => 'Certificate', 'Masters' => 'Masters'])
            ->required();

    $row = $form->addRow();
        $row->addLabel('studentLevel', __('Student Level'));
        $row->addSelect('studentLevel')
            ->fromArray(['Freshman' => 'Freshman', 'Sophomore' => 'Sophomore', 'Junior' => 'Junior', 'Senior' => 'Senior', 'M1' => 'M1', 'M2' => 'M2', 'M3' => 'M3']);

    $row = $form->addRow();
        $row->addLabel('startDate', __('Start Date'))->description(__('Required'));
        $row->addDate('startDate')->required();

    $row = $form->addRow();
        $row->addLabel('switchDate', __('Switch Date'));
        $row->addDate('switchDate');

    $row = $form->addRow();
        $row->addLabel('graduationDate', __('Graduation Date'));
        $row->addDate('graduationDate');

    $row = $form->addRow();
        $row->addLabel('status', __('Status'));
        $row->addSelect('status')
            ->fromArray(['Active' => 'Active', 'Switched' => 'Switched', 'Graduated' => 'Graduated', 'Withdrawn' => 'Withdrawn', 'On Leave' => 'On Leave'])
            ->required();

    $row = $form->addRow();
        $row->addLabel('notes', __('Notes'));
        $row->addTextArea('notes')->setRows(3);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Save'));

    echo $form->getOutput();

    // Display existing records in a table
    echo "<h3 class='mt-6'>" . __('Student Program Records') . "</h3>";

    $allPrograms = $programGateway->getAllProgramsByPerson($_SESSION[$guid]['gibbonPersonID'] ?? 0);
    
    if (!empty($allPrograms)) {
        echo "<table class='w-full' cellspacing='0'>";
        echo "<thead>";
        echo "<tr class='bg-gray-100'>";
        echo "<th class='p-2 text-left text-sm font-semibold'>" . __('Student') . "</th>";
        echo "<th class='p-2 text-left text-sm font-semibold'>" . __('Program') . "</th>";
        echo "<th class='p-2 text-left text-sm font-semibold'>" . __('Level') . "</th>";
        echo "<th class='p-2 text-left text-sm font-semibold'>" . __('Status') . "</th>";
        echo "<th class='p-2 text-left text-sm font-semibold'>" . __('Start Date') . "</th>";
        echo "<th class='p-2 text-left text-sm font-semibold'>" . __('Graduation') . "</th>";
        echo "</tr>";
        echo "</thead>";
        echo "<tbody>";
        
        foreach ($allPrograms as $program) {
            $statusClass = 'badge-info';
            if ($program['status'] === 'Graduated') {
                $statusClass = 'badge-success';
            } elseif ($program['status'] === 'Withdrawn') {
                $statusClass = 'badge-danger';
            }
            
            echo "<tr class='border-t hover:bg-gray-50'>";
            echo "<td class='p-2'>" . htmlspecialchars($program['gibbonPersonID']) . "</td>";
            echo "<td class='p-2'>" . htmlspecialchars($program['programType'] . ' - ' . $program['concentration']) . "</td>";
            echo "<td class='p-2'>" . htmlspecialchars($program['studentLevel'] ?? '-') . "</td>";
            echo "<td class='p-2'><span class='badge {$statusClass}'>" . htmlspecialchars($program['status']) . "</span></td>";
            echo "<td class='p-2'>" . ($program['startDate'] ? date('Y-m-d', strtotime($program['startDate'])) : '-') . "</td>";
            echo "<td class='p-2'>" . ($program['graduationDate'] ? date('Y-m-d', strtotime($program['graduationDate'])) : '-') . "</td>";
            echo "</tr>";
        }
        
        echo "</tbody>";
        echo "</table>";
    } else {
        echo "<p class='text-gray-600 italic'>" . __('No program records found.') . "</p>";
    }
}
