<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Export rendez-vous</title>
</head>
<body>
    <h1>Export rendez-vous</h1>

    <table width="100%" border="1" cellspacing="0" cellpadding="6">
        <thead>
            <tr>
                <th>ID</th>
                <th>Client</th>
                <th>Employé</th>
                <th>Zone</th>
                <th>Date</th>
                <th>Heure</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rdv): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($rdv->id); ?></td>
                    <td><?php echo e($rdv->client?->name); ?></td>
                    <td><?php echo e($rdv->employe?->name); ?></td>
                    <td><?php echo e($rdv->serviceZone?->name); ?></td>
                    <td><?php echo e($rdv->date); ?></td>
                    <td><?php echo e($rdv->heure); ?></td>
                    <td><?php echo e($rdv->status); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
    </table>
</body>
</html><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/exports/rendez-vous-pdf.blade.php ENDPATH**/ ?>