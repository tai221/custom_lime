<?php
/**
 * Personal settings edition
 */
?>

<div class="container">
<?php echo CHtml::form($this->createUrl("/admin/user/sa/personalsettings"), 'post', array('class' => 'form44 ', 'id'=>'personalsettings','autocomplete'=>"off")); ?>
    <div class="row">
        <div class="col-xs-12">
            <ul class="nav nav-tabs" role="tablist">
                <li role="presentation" class="active"><a href="#your-profile" role="tab" data-toggle="tab"><?php eT("My profile"); ?></a></li>
            </ul>
            <div class="tab-content">
                <!-- TAB: My profile settings -->
                <div role="tabpanel" class="tab-pane fade in active" id="your-profile">
                    <div class="pagetitle h3"><?php eT("My profile"); ?></div>
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-sm-12 col-md-12">
                                <div class="form-group">
                                    <?php echo CHtml::label(gT("User name:"), 'lang', array('class'=>" control-label")); ?>
                                    <div class="">
                                        <?php echo CHtml::textField('username', $sUsername,array('class'=>'form-control','readonly'=>'readonly')); ?>
                                    </div>
                                    <div class="">
                                        <span class='text-info'><?php eT("The user name cannot be changed."); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <hr/>
                        </div>
                        <div class="row">
                            <div class="col-sm-12 col-md-6">
                                <div class="form-group">
                                    <?php echo CHtml::label(gT("Email:"), 'lang', array('class'=>" control-label")); ?>
                                    <div class="">
                                        <?php echo CHtml::emailField('email', $sEmailAdress,array('class'=>'form-control','maxlength'=>254)); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-12 col-md-6">
                                <div class="form-group">
                                    <?php echo CHtml::label(gT("Full name:"), 'lang', array('class'=>" control-label")); ?>
                                    <div class="">
                                        <?php echo CHtml::textField('fullname', $sFullname ,array('class'=>'form-control','maxlength'=>50)); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <hr/>
                        </div>
                        <div class="row">
                            <div class="col-md-3">
                                <button class="btn btn-default btn-warning" id="selector__showChangePassword" style="color: white; outline: none;">
                                    <i class="fa fa-lock"></i>
                                    <?=gT("Change password")?>
                                </button>

                                <br/>
                            </div>
                        </div>
                        <div class="row selector__password-row hidden">
                            <input type="hidden" id="newpasswordshown" name="newpasswordshown" value="0" />
                            <div class="col-md-6">
                                <div class="form-group">
                                    <?php echo CHtml::label(gT("Current password:"), 'lang', array('class'=>"control-label")); ?>
                                    <div class="">
                                        <?php echo CHtml::passwordField('oldpassword', '',array('disabled'=>true, 'class'=>'form-control','autocomplete'=>"off",'placeholder'=>html_entity_decode(str_repeat("&#9679;",10),ENT_COMPAT,'utf-8'))); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <?php echo CHtml::label(gT("New password:"), 'lang', array('class'=>" control-label")); ?>
                                    <div class="">
                                        <?php echo CHtml::passwordField('password', '',array('disabled'=>true, 'class'=>'form-control','autocomplete'=>"off",'placeholder'=>html_entity_decode(str_repeat("&#9679;",10),ENT_COMPAT,'utf-8'))); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <?php echo CHtml::label(gT("Repeat new password:"), 'lang', array('class'=>" control-label")); ?>
                                    <div class="">
                                        <?php echo CHtml::passwordField('repeatpassword', '',array('disabled'=>true, 'class'=>'form-control','autocomplete'=>"off",'placeholder'=>html_entity_decode(str_repeat("&#9679;",10),ENT_COMPAT,'utf-8'))); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

        <!-- Buttons -->
        <p>
            <?php echo CHtml::hiddenField('action', 'savepersonalsettings'); ?>
            <?php echo CHtml::submitButton(gT("Save settings",'unescaped'),array('class' => 'hidden')); ?>
        </p>
    <?php echo CHtml::endForm(); ?>

</div>

<?php App()->getClientScript()->registerScript("personalSettings", "
$('#selector__showChangePassword').on('click', function(e){
    e.preventDefault();
    $('#newpasswordshown').val('1');
    $('.selector__password-row').removeClass('hidden').find('input').each(
        function(i,item){
            $(item).prop('disabled', false);
        }
    );
    $(this).closest('div').remove();
});
", LSYii_ClientScript::POS_POSTSCRIPT);
