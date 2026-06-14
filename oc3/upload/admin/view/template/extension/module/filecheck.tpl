<?php echo $header; ?><?php echo $column_left; ?>
<div id="content">
    <div class="page-header">
        <div class="container-fluid">
            <div class="pull-right">
                <button type="submit" form="form-filecheck" data-toggle="tooltip" title="<?php echo $button_save; ?>" class="btn btn-primary">
                    <i class="fa fa-save"></i> <?php echo $button_save; ?>
                </button>
                <a href="<?php echo $cancel; ?>" data-toggle="tooltip" title="<?php echo $button_cancel; ?>" class="btn btn-default">
                    <i class="fa fa-reply"></i> <?php echo $button_cancel; ?>
                </a>
            </div>
            <h1><?php echo $heading_title; ?></h1>
            <ul class="breadcrumb">
                <?php foreach ($breadcrumbs as $bc) { ?>
                    <li><a href="<?php echo $bc['href']; ?>"><?php echo $bc['text']; ?></a></li>
                <?php } ?>
            </ul>
        </div>
    </div>

    <div class="container-fluid">
        <?php if ($error_warning) { ?>
            <div class="alert alert-danger alert-dismissible"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php } ?>
        <?php if ($success) { ?>
            <div class="alert alert-success alert-dismissible"><i class="fa fa-check-circle"></i> <?php echo $success; ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php } ?>

        <div class="panel panel-default">
            <div class="panel-heading"><h3 class="panel-title"><i class="fa fa-pencil"></i> <?php echo $text_edit; ?></h3></div>
            <div class="panel-body">
                <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-filecheck" class="form-horizontal">

                    <!-- API Credentials -->
                    <fieldset>
                        <legend>API Credentials</legend>

                        <div class="form-group">
                            <label class="col-sm-2 control-label"><?php echo $entry_status; ?></label>
                            <div class="col-sm-10">
                                <select name="module_filecheck_status" class="form-control">
                                    <option value="1" <?php echo ($module_filecheck_status == '1') ? 'selected' : ''; ?>><?php echo $text_enabled; ?></option>
                                    <option value="0" <?php echo ($module_filecheck_status != '1') ? 'selected' : ''; ?>><?php echo $text_disabled; ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-2 control-label"><?php echo $entry_pk; ?></label>
                            <div class="col-sm-10">
                                <input type="text" name="module_filecheck_publishable_key" value="<?php echo htmlspecialchars($module_filecheck_publishable_key, ENT_QUOTES); ?>" class="form-control" placeholder="pk_live_...">
                                <p class="help-block"><?php echo $help_pk; ?></p>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-2 control-label"><?php echo $entry_sk; ?></label>
                            <div class="col-sm-10">
                                <input type="password" name="module_filecheck_secret_key" value="<?php echo htmlspecialchars($module_filecheck_secret_key, ENT_QUOTES); ?>" class="form-control" placeholder="sk_live_...">
                                <p class="help-block"><?php echo $help_sk; ?></p>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-2 control-label"><?php echo $entry_agent_id; ?></label>
                            <div class="col-sm-10">
                                <input type="text" name="module_filecheck_agent_id" value="<?php echo htmlspecialchars($module_filecheck_agent_id, ENT_QUOTES); ?>" class="form-control" placeholder="agt_...">
                                <p class="help-block"><?php echo $help_agent_id; ?></p>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-2 control-label"><?php echo $entry_api_url; ?></label>
                            <div class="col-sm-10">
                                <input type="text" name="module_filecheck_api_url" value="<?php echo htmlspecialchars($module_filecheck_api_url, ENT_QUOTES); ?>" class="form-control" placeholder="https://api.filecheck.io">
                                <p class="help-block"><?php echo $help_api_url; ?></p>
                            </div>
                        </div>
                    </fieldset>

                    <!-- Global Configuration -->
                    <fieldset>
                        <legend>Global Configuration</legend>

                        <div class="form-group">
                            <label class="col-sm-2 control-label"><?php echo $entry_workflow; ?></label>
                            <div class="col-sm-10">
                                <select name="module_filecheck_default_workflow_id" class="form-control" style="min-width:300px;">
                                    <option value=""><?php echo $text_select; ?></option>
                                    <?php foreach ($workflows as $wf) { ?>
                                        <?php if (isset($wf['id']) && isset($wf['title'])) { ?>
                                            <option value="<?php echo htmlspecialchars($wf['id'], ENT_QUOTES); ?>"
                                                <?php echo ($module_filecheck_default_workflow_id == $wf['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($wf['title'] . ' (' . $wf['id'] . ')', ENT_QUOTES); ?>
                                            </option>
                                        <?php } ?>
                                    <?php } ?>
                                </select>
                                <p class="help-block"><?php echo $help_workflow; ?></p>
                            </div>
                        </div>
                    </fieldset>

                </form>
            </div>
        </div>

        <!-- Connection Test -->
        <div class="panel panel-default">
            <div class="panel-heading"><h3 class="panel-title"><i class="fa fa-plug"></i> <?php echo $text_connection_test; ?></h3></div>
            <div class="panel-body">
                <p>Test that your API keys can communicate with the Filecheck servers.</p>
                <button type="button" id="fc-test-connection" class="btn btn-info"
                    data-ajax="<?php echo $ajax_url; ?>">
                    <i class="fa fa-refresh"></i> <?php echo $button_test; ?>
                </button>
                <span id="fc-connection-result" style="margin-left:12px;font-weight:500;"></span>
            </div>
        </div>
    </div>
</div>
<?php echo $footer; ?>
