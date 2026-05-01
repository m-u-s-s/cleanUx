<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Export feedbacks</title>
</head>
<body>
    <h1>Export feedbacks</h1>

    <table width="100%" border="1" cellspacing="0" cellpadding="6">
        <thead>
            <tr>
                <th>ID</th>
                <th>Client</th>
                <th>Note</th>
                <th>Commentaire</th>
                <th>Zone</th>
            </tr>
        </thead>
        <tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $feedbacks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $feedback): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($feedback->id); ?></td>
                    <td><?php echo e($feedback->client?->name); ?></td>
                    <td><?php echo e($feedback->note); ?></td>
                    <td><?php echo e($feedback->commentaire); ?></td>
                    <td><?php echo e($feedback->rendezVous?->serviceZone?->name); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
    </table>
</body>
</html><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/exports/feedbacks-pdf.blade.php ENDPATH**/ ?>