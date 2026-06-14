<div class="tab-content" style="padding:15px 0;">
    <div class="form-group">
        <label class="col-sm-2 control-label"><?php echo $text_workflow; ?></label>
        <div class="col-sm-10">
            <select name="filecheck_workflow_id" class="form-control" style="min-width:300px;">
                <option value="none"   <?php echo ($settings['workflow_id'] === 'none'   || !$settings['workflow_id']) ? 'selected' : ''; ?>>
                    <?php echo $text_none; ?>
                </option>
                <option value="global" <?php echo ($settings['workflow_id'] === 'global') ? 'selected' : ''; ?>>
                    <?php echo $text_global; ?>
                    <?php echo $default_workflow_id ? ' (' . htmlspecialchars($default_workflow_id, ENT_QUOTES) . ')' : ''; ?>
                </option>
                <?php foreach ($workflows as $wf) { ?>
                    <?php if (isset($wf['id']) && isset($wf['title'])) { ?>
                        <option value="<?php echo htmlspecialchars($wf['id'], ENT_QUOTES); ?>"
                            <?php echo ($settings['workflow_id'] === $wf['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($wf['title'] . ' (' . $wf['id'] . ')', ENT_QUOTES); ?>
                        </option>
                    <?php } ?>
                <?php } ?>
            </select>
            <p class="help-block">Select the Filecheck workflow for this product. "None" disables the widget.</p>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-2 control-label"><?php echo $text_connector; ?></label>
        <div class="col-sm-10">
            <select name="filecheck_connector_id" class="form-control" style="min-width:300px;">
                <option value=""><?php echo $text_none; ?></option>
                <?php foreach ($connectors as $cn) { ?>
                    <?php if (isset($cn['id']) && isset($cn['title'])) { ?>
                        <option value="<?php echo htmlspecialchars($cn['id'], ENT_QUOTES); ?>"
                            <?php echo ($settings['connector_id'] === $cn['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cn['title'] . ' (' . $cn['id'] . ')', ENT_QUOTES); ?>
                        </option>
                    <?php } ?>
                <?php } ?>
            </select>
            <p class="help-block">Optional. Syncs Filecheck file details back to elements on this product page.</p>
        </div>
    </div>

    <input type="hidden" name="filecheck_product_id_loaded" value="<?php echo (int)$product_id; ?>">
</div>
