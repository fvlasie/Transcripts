<?php

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Services\Format;

require_once __DIR__.'/moduleFunctions.php';
checkAndMigrateTranscriptsSchema($pdo);
checkAndMigrateTranscriptsSettings($pdo);

if (isActionAccessible($guid, $connection2, '/modules/Transcripts/template_manage.php') == false) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Transcript Template'));

    $page->return->addReturns([
        'success0' => __('Transcript assets saved successfully.'),
        'warning1' => __('Settings were saved, but one or more files could not be uploaded.'),
    ]);

    $settingGateway = $container->get(SettingGateway::class);

    $settings = [
        'customAssetPath' => $settingGateway->getSettingByScope('Transcripts', 'customAssetPath'),
        'page1BackgroundPath' => $settingGateway->getSettingByScope('Transcripts', 'page1BackgroundPath'),
        'page2BackgroundPath' => $settingGateway->getSettingByScope('Transcripts', 'page2BackgroundPath'),
        'registrarSignaturePath' => $settingGateway->getSettingByScope('Transcripts', 'registrarSignaturePath'),
        'registrarGibbonPersonID' => $settingGateway->getSettingByScope('Transcripts', 'registrarGibbonPersonID'),
    ];

    echo '<h2>';
    echo __('Current Assets');
    echo '</h2>';

    $registrarPersonID = (int)($settings['registrarGibbonPersonID'] ?? 0);
    $registrarDisplay = __('Not set');
    if ($registrarPersonID > 0) {
        $registrarPerson = $container->get(UserGateway::class)->getByID($registrarPersonID, ['officialName', 'preferredName', 'surname', 'username']);
        if (!empty($registrarPerson)) {
            $registrarDisplay = trim((string)($registrarPerson['officialName'] ?? ''));
            if ($registrarDisplay === '') {
                $registrarDisplay = Format::name('', $registrarPerson['preferredName'], $registrarPerson['surname'], 'Staff', true);
            }
            $registrarDisplay .= ' ('.$registrarPerson['username'].')';
        }
    }

    echo '<ul>';
    echo '<li><strong>'.__('Page 1 Background').':</strong> '.(!empty($settings['page1BackgroundPath']) ? htmlspecialchars($settings['page1BackgroundPath']) : __('Not uploaded')).'</li>';
    echo '<li><strong>'.__('Page 2 Background').':</strong> '.(!empty($settings['page2BackgroundPath']) ? htmlspecialchars($settings['page2BackgroundPath']) : __('Not uploaded')).'</li>';
    echo '<li><strong>'.__('Registrar Signature').':</strong> '.(!empty($settings['registrarSignaturePath']) ? htmlspecialchars($settings['registrarSignaturePath']) : __('Not uploaded')).'</li>';
    echo '<li><strong>'.__('Registrar User').':</strong> '.htmlspecialchars($registrarDisplay).'</li>';
    echo '</ul>';

    echo '<p>'.__('Reference backgrounds ship with the module at').' <code>modules/Transcripts/assets/backgrounds/page1.pdf</code> '.__('and').' <code>page2.pdf</code>.</p>';
    echo '<p>'.__('Upload vector PDF backgrounds with artwork only (logo, borders, footer graphics). The template already includes the school header and “Transcript of Academic Record” title — generated content starts below that artwork.').'</p>';

    echo '<h2>';
    echo __('Upload');
    echo '</h2>';

    $form = Form::create('templateManage', $session->get('absoluteURL').'/modules/'.$session->get('module').'/template_manageProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $session->get('address'));

    $row = $form->addRow();
        $row->addLabel('page1BackgroundFile', __('Page 1 Background PDF'))->description(__('Full header, logo, and page frame. Vector PDF recommended.'));
        $row->addFileUpload('page1BackgroundFile')->accepts(['.pdf']);

    $row = $form->addRow();
        $row->addLabel('page2BackgroundFile', __('Page 2 Background PDF'))->description(__('Continuation pages with minor header and footer artwork.'));
        $row->addFileUpload('page2BackgroundFile')->accepts(['.pdf']);

    $row = $form->addRow();
        $row->addLabel('registrarSignatureFile', __('Registrar Signature'))->description(__('PNG or JPG signature image with transparent background preferred.'));
        $row->addFileUpload('registrarSignatureFile')->accepts(['.png', '.jpg', '.jpeg', '.webp']);

    $staffOptions = [];
    $staffRows = $pdo->select("SELECT gibbonPerson.gibbonPersonID, title, surname, preferredName, username
        FROM gibbonPerson
        JOIN gibbonStaff ON (gibbonPerson.gibbonPersonID=gibbonStaff.gibbonPersonID)
        WHERE gibbonPerson.status='Full'
        ORDER BY surname, preferredName");

    foreach ($staffRows as $staff) {
        $staffOptions[$staff['gibbonPersonID']] = Format::name($staff['title'], $staff['preferredName'], $staff['surname'], 'Staff', true, true).' ('.$staff['username'].')';
    }

    if ($registrarPersonID > 0 && !isset($staffOptions[$registrarPersonID]) && !empty($registrarPerson)) {
        $staffOptions[$registrarPersonID] = Format::name('', $registrarPerson['preferredName'], $registrarPerson['surname'], 'Staff', true).' ('.$registrarPerson['username'].')';
    }

    $row = $form->addRow();
        $row->addLabel('registrarGibbonPersonID', __('Registrar User'))->description(__('Only this user may generate official signed transcripts. Their Official Name is used on signed PDFs. Choose None to clear the assignment.'));
        $row->addSelect('registrarGibbonPersonID')
            ->fromArray(['' => __('None')] + $staffOptions)
            ->selected($registrarPersonID > 0 ? (string) $registrarPersonID : '');

    $row = $form->addRow();
        $row->addLabel('customAssetPath', __('Storage Folder'))->description(__('Relative path where transcript assets are stored.'));
        $row->addTextField('customAssetPath')->required()->setValue($settings['customAssetPath'] ?: '/uploads/transcripts');

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Save'));

    echo $form->getOutput();

    echo '<h2>';
    echo __('Layout');
    echo '</h2>';

    echo '<p>'.__('Transcript typography and tables are rendered from:').'</p>';
    echo '<p><code>modules/Transcripts/templates/transcriptOfficial.php</code></p>';
    echo '<p><code>modules/Transcripts/templates/transcriptOfficial.css</code></p>';
}
