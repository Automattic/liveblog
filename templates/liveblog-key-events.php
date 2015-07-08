<div class="liveblog-key-events">
    <h2>Key Events</h2>
    <div id="liveblog-key-entries">
        <?php foreach ( (array) $entries as $entry ) : ?>

            <?php echo $entry->render(); ?>

        <?php endforeach; ?>
    </div>
</div>