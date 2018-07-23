<?php
/**
 * Global layout for specific part for admin
 *
 * @author Denis Chenu <denis@sondages.pro>
 * @copyright 2018 Denis Chenu <http://www.sondages.pro>
 * @license GPL v3
 * @version 0.0.1
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
?>
<!DOCTYPE html>
<html lang="<?php echo App()->getLanguage(); ?>">
  <head>
    <!-- Meta, title, CSS, favicons, etc. -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo flattenText($title); ?></title>
    <link rel="shortcut icon" href="<?php echo $oTemplate->sTemplateurl ?>files/favicon.ico" />
    <?php
        App()->getClientScript()->registerPackage("bootsrap");
        App()->getClientScript()->registerCssFile($assetUrl."/responselistandmanage.css");
        App()->getClientScript()->registerScriptFile($assetUrl."/responselistandmanage.js");
    ?>
  </head>
 <body>
    <div class="navbar navbar-default navbar-fixed-top">
        <div class="navbar-header">
            <div class="navbar-brand" href="#"><?php echo CHtml::link(
                CHtml::tag("div",array('class'=>'text-muted'),$title),
                array("plugins/direct","plugin"=>$pluginName)
            ); ?>
            </div>
        </div>
        <?php if($username) { ?>
        <nav>
            <ul class="nav nav-pills pull-right">
                <?php
                echo CHtml::tag('li',array('class'=>"dropdown"),"",false);
                echo CHtml::tag('a',array('class'=>"dropdown-toggle",'aria-expanded'=>'false','aria-haspopup'=>'true','role'=>'button','data-toggle'=>'dropdown'),Yii::app()->user->getName().' <b class="caret"></b>');
                echo CHtml::tag('ul',array('class'=>"dropdown-menu"),"",false);
                if($showAdmin) {
                    echo CHtml::tag('li',array(),
                        CHtml::link(gt("Administration"),array('/admin/index'))
                        );
                }
                echo CHtml::tag('li',array(),
                    CHtml::link(gt("Logout"),array('/admin/authentication','sa' => 'logout'))
                    );
                echo CHtml::closeTag('ul');
                echo CHtml::closeTag('li');
                ?>
            </ul>
        </nav>
        <?php } ?>
    </div>

    <div class="container-fluid">
      <?php
        Yii::app()->getController()->renderPartial($pluginName.".views.".$subview,$_data_);
      ?>
    </div> <!-- /container -->
</body>
