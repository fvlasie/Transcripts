<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        <?php echo file_get_contents(__DIR__.'/transcriptOfficial.css'); ?>
    </style>
</head>
<body>
    <div class="transcript-content">
        <?php if (empty($layout['isOfficial']) && !empty($layout['unofficialNotice'])): ?>
        <div class="unofficial-banner"><?php echo htmlspecialchars($layout['unofficialNotice']); ?></div>
        <?php endif; ?>

        <div class="cumulative-gpa">
            Cumulative Weighted Grade Point Average:
            <strong><?php echo htmlspecialchars(number_format((float)($layout['cumulativeGPA'] ?? 0), 2)); ?></strong>
        </div>

        <?php if (!empty($layout['student'])): ?>
        <div class="student-information">
            <h2>Student Information</h2>
            <table class="info-table">
                <tr>
                    <td class="label">Name:</td>
                    <td><?php echo htmlspecialchars($layout['student']['name'] ?? ''); ?></td>
                </tr>
                <?php if (!empty($layout['student']['identifier'])): ?>
                <tr>
                    <td class="label">Social Security/Passport Number:</td>
                    <td><?php echo htmlspecialchars($layout['student']['identifier']); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td class="label">Address:</td>
                    <td><?php echo htmlspecialchars($layout['student']['address'] ?? ''); ?></td>
                </tr>
                <tr>
                    <td class="label">Date of Birth:</td>
                    <td><?php echo htmlspecialchars($layout['student']['dateOfBirth'] ?? ''); ?></td>
                </tr>
                <?php if (!empty($layout['student']['degreeProgram'])): ?>
                <tr>
                    <td class="label">Degree Program:</td>
                    <td><?php echo htmlspecialchars($layout['student']['degreeProgram']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($layout['student']['dateAdmitted'])): ?>
                <tr>
                    <td class="label">Date Admitted:</td>
                    <td><?php echo htmlspecialchars($layout['student']['dateAdmitted']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($layout['student']['dateGraduated'])): ?>
                <tr>
                    <td class="label">Date of Graduation:</td>
                    <td><?php echo htmlspecialchars($layout['student']['dateGraduated']); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <?php endif; ?>

        <?php foreach ($layout['terms'] as $term): ?>
        <div class="term-block">
            <div class="term-heading">
                <span class="term-year"><?php echo htmlspecialchars($term['schoolYear']); ?></span>
                <span class="term-name"><?php echo htmlspecialchars($term['term']); ?></span>
            </div>

            <table class="course-table">
                <thead>
                    <tr>
                        <th class="col-course">Course</th>
                        <th class="col-grade">Grade</th>
                        <th class="col-points">Points</th>
                        <th class="col-biblical">Biblical/Theological</th>
                        <th class="col-general">General</th>
                        <th class="col-professional">Professional</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($term['courses'] as $course): ?>
                    <tr>
                        <td class="col-course"><?php echo $course['courseNameHtml'] ?? htmlspecialchars($course['courseName']); ?></td>
                        <td class="col-grade"><?php echo htmlspecialchars($course['letterGrade']); ?></td>
                        <td class="col-points"><?php echo $course['gpaPoints'] !== null ? htmlspecialchars(number_format((float)$course['gpaPoints'], 1)) : '-'; ?></td>
                        <td class="col-biblical"><?php echo $course['biblicalCredits'] > 0 ? htmlspecialchars(number_format($course['biblicalCredits'], 0)) : ''; ?></td>
                        <td class="col-general"><?php echo $course['generalCredits'] > 0 ? htmlspecialchars(number_format($course['generalCredits'], 0)) : ''; ?></td>
                        <td class="col-professional"><?php echo $course['professionalCredits'] > 0 ? htmlspecialchars(number_format($course['professionalCredits'], 0)) : ''; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="term-summary-row term-summary-start">
                        <td class="col-course">Term Grade Point Average:</td>
                        <td class="col-grade"></td>
                        <td class="col-points summary-value"><?php echo $term['termGPA'] !== null ? htmlspecialchars(number_format($term['termGPA'], 2)) : '-'; ?></td>
                        <td class="col-concentration-label" colspan="3"><em>Term Credits per Concentration:</em></td>
                    </tr>
                    <tr class="term-summary-row">
                        <td class="col-course">Total Term Credits:</td>
                        <td class="col-points summary-value"><?php echo htmlspecialchars(number_format($term['totalCredits'], 0)); ?></td>
                        <td class="col-grade"></td>
                        <td class="col-biblical summary-value"><?php echo htmlspecialchars(number_format($term['biblicalCredits'], 0)); ?></td>
                        <td class="col-general summary-value"><?php echo htmlspecialchars(number_format($term['generalCredits'], 0)); ?></td>
                        <td class="col-professional summary-value"><?php echo htmlspecialchars(number_format($term['professionalCredits'], 0)); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>

        <?php if (!empty($layout['isOfficial']) && (!empty($layout['registrar']['name']) || !empty($layout['registrar']['signatureFile'])) || !empty($layout['student']['graduationBanner']) || (!empty($layout['unofficialNotice']) && empty($layout['isOfficial']))): ?>
        <div class="transcript-closing">
            <?php if (!empty($layout['isOfficial']) && (!empty($layout['registrar']['name']) || !empty($layout['registrar']['signatureFile']))): ?>
            <div class="registrar-block">
                <div>Transcript Verified by: <?php echo htmlspecialchars($layout['registrar']['name'] ?? ''); ?>, Registrar</div>
                <?php if (!empty($layout['registrar']['signatureFile'])): ?>
                <img src="<?php echo htmlspecialchars($layout['registrar']['signatureFile']); ?>" class="registrar-signature" alt="">
                <?php endif; ?>
            </div>
            <?php elseif (!empty($layout['unofficialNotice'])): ?>
            <div class="unofficial-notice"><?php echo htmlspecialchars($layout['unofficialNotice']); ?></div>
            <?php endif; ?>

            <?php if (!empty($layout['student']['graduationBanner'])): ?>
            <div class="graduation-banner"><?php echo htmlspecialchars($layout['student']['graduationBanner']); ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <htmlpagefooter name="transcriptFooter">
        <div class="page-number">Page {PAGENO} of {nbpg}</div>
    </htmlpagefooter>
</body>
</html>
