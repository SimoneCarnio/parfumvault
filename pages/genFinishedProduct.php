<?php 
if (!defined('pvault_panel')){ die('Not Found');}  
if($_POST['formula']){
	$f_name =  mysqli_real_escape_string($conn, $_POST['formula']);
	$meta = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM formulasMetaData WHERE name = '$f_name'"));

	$formula_q = mysqli_query($conn, "SELECT * FROM formulas WHERE name = '$f_name' ORDER BY ingredient ASC");
	
	$mg = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(quantity) AS total_mg FROM formulas WHERE name = '$f_name'"));
	
	$bottle = mysqli_real_escape_string($conn, $_POST['bottle']);
	$type = mysqli_real_escape_string($conn, $_POST['type']);
	$carrier_id = mysqli_real_escape_string($conn, $_POST['carrier']);
	$lid_id = mysqli_real_escape_string($conn, $_POST['lid']);
	$bottle_cost = mysqli_fetch_array(mysqli_query($conn, "SELECT  price,ml,name FROM bottles WHERE id = '$bottle'"));
	$carrier_cost = mysqli_fetch_array(mysqli_query($conn, "SELECT  price,ml FROM ingredients WHERE id = '$carrier_id'"));
	$defCatClass = mysqli_real_escape_string($conn, $_POST['defCatClass']);

	if(empty($defCatClass)){
		$defCatClass = $settings['defCatClass'];
	}
	
	if($_POST['lid']){
		$lid_cost = mysqli_fetch_array(mysqli_query($conn, "SELECT  price,style FROM lids WHERE id = '$lid_id'"));
	}else{
		$lid_cost['price'] = 0;
		$lid_cost['style'] = 'none';
	}
	
	$bottle = $bottle_cost['ml'];
	$new_conc = $bottle/100*$type;
	$carrier = $bottle - $new_conc;
	
	if(validateFormula($meta['fid'], $bottle, $new_conc, $mg['total_mg'], $defCatClass, $conn) == TRUE){
		$msg =  '<div class="alert alert-danger alert-dismissible">Your formula contains materials, exceeding and/or missing IFRA standards. Please alter your formula.</div>';
	}

	
	if($_POST['batchID'] == '1'){
		if (!file_exists($uploads_path.'batches')) {
			mkdir($uploads_path.'batches', 0740, true);
        }
		define('FPDF_FONTPATH','./fonts');
		$batchID = genBatchID();
		$fid = base64_encode($f_name);
		$batchFile = $uploads_path.'batches/'.$batchID;
		
		mysqli_query($conn, "INSERT INTO batchIDHistory (id,fid,pdf) VALUES ('$batchID','$fid','$batchFile')");
		genBatchPDF($fid,$batchID,$bottle,$new_conc,$mg['total_mg'],$ver,$uploads_path,$conn);
	}else{
		$batchID = 'N/A';
	}
}
?>
<script>
function printLabel() {
	<?php if(empty($settings['label_printer_addr']) || empty($settings['label_printer_model'])){?>
	$("#inf").html('<div class="alert alert-danger alert-dismissible">Please configure printer details in <a href="?do=settings">settings<a> page</div>');
	<?php }else{ ?>
	$("#inf").html('<div class="alert alert-info alert-dismissible">Printing...</div>');

$.ajax({ 
    url: 'pages/manageFormula.php', 
	type: 'get',
    data: {
		action: "printLabel",
		batchID: "<?php echo $batchID; ?>",
		name: "<?php echo $f_name; ?>"
		},
	dataType: 'html',
    success: function (data) {
	  $('#inf').html(data);
    }
  });
	<?php } ?>
};

function printBoxLabel() {
	<?php if(empty($settings['label_printer_addr']) || empty($settings['label_printer_model']) || $settings['label_printer_size'] != '62 --red'){?>
	$("#inf").html('<div class="alert alert-danger alert-dismissible">Please configure printer details in <a href="?do=settings">settings<a> page. Note: For this label you need 62mm label</div>');
	<?php }else{ ?>
	$("#inf").html('<div class="alert alert-info alert-dismissible">Printing...</div>');

$.ajax({ 
    url: 'pages/manageFormula.php', 
	type: 'get',
    data: {
		action: "printBoxLabel",
		batchID: "<?php echo $batchID; ?>",
		name: "<?php echo $f_name; ?>",
		carrier: "<?php echo $carrier*100/$bottle;?>",
		copies: $("#copiesToPrint").val()
		},
	dataType: 'html',
    success: function (data) {
	  $('#printBoxLabel').modal('toggle');
	  $('#inf').html(data);
    }
  });
	<?php } ?>
};

function downloadBoxLabel() {
	$.ajax({ 
    url: 'pages/manageFormula.php', 
	type: 'get',
    data: {
		action: "downloadBoxLabel",
		batchID: "<?php echo $batchID; ?>",
		name: "<?php echo $f_name; ?>",
		carrier: "<?php echo $carrier*100/$bottle;?>",
		},
	dataType: 'html',
    success: function (data) {
	  $('#inf').html(data);
    }
  });
};
</script>
<div id="content-wrapper" class="d-flex flex-column">
<?php require_once('pages/top.php'); ?>
        <div class="container-fluid">
		<div>
          <div class="card shadow mb-4">
            <div class="card-header py-3"> 
            <?php if($_GET['generate'] && $_POST['formula']){?>
             <h2 class="m-0 font-weight-bold text-primary"><a href="?do=genFinishedProduct"><?php echo $meta['product_name'];?></a></h2>
             <h5 class="m-1 text-primary">Formula name: <strong><?php echo $meta['name'];?></strong></h5>
             <h5 class="m-1 text-primary">Bottle: <strong><?php echo $bottle; ?>ml</strong></h5>
			 <h5 class="m-1 text-primary">Concentration: <strong><?php echo $type; ?>%</h5>
             <h5 class="m-1 text-primary"><?php if($_POST['batchID'] == '1'){ echo 'Batch ID: <a href="'.$uploads_path.'batches/'.$batchID.'" target="_blank">'.$batchID.'</a>'; }else{ echo 'Batch ID: <a href="#">N/A</a>';}?></h5>
             <h5 class="m-1 text-primary">Category Class: <strong><?php echo ucfirst($defCatClass);?></strong></h5>
        	<?php }else{ ?>
              <h2 class="m-0 font-weight-bold text-primary"><a href="?do=genFinishedProduct">Generate Finished Product</a></h2>
            <?php } ?>
            </div>
            <div class="card-body">
           <div id="inf"><?php if($msg){ echo $msg; }?></div>
            <?php if($_GET['generate'] && $_POST['formula']){?>
              <div>
                <table class="table table-bordered" id="formula" width="100%" cellspacing="0">
                  <thead>
                    <tr class="noexport">
                      <th colspan="8">
                     <div class="text-left">
                      <div class="btn-group">
                      <button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fa fa-bars"></i></button>
                      <div class="dropdown-menu">
                        <a class="dropdown-item" id="pdf" href="#">Export to PDF</a>
                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#IFRA">IFRA Certificate</a>
                        <a class="dropdown-item" href="javascript:printLabel()" onclick="return confirm('Print label?')">Print Label</a>
                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#printBoxLabel">Print Box Label</a>
                        <a class="dropdown-item" href="javascript:downloadBoxLabel()">Download Box Label</a>
                      </div>
                    </div>
                    </div>
                    </th>
                    </tr>
                    <tr>
                      <th width="22%">Ingredient</th>
                      <th width="10%">CAS#</th>
                      <th width="10%">Purity %</th>
                      <th width="10%">Dilutant</th>
                      <th width="10%">Quantity</th>
                      <th width="10%">Concentration*</th>
                      <th colspan="2">Cost</th>
                    </tr>
                  </thead>
                  <?php while ($formula = mysqli_fetch_array($formula_q)) {
					    $ing_q = mysqli_fetch_array(mysqli_query($conn, "SELECT cas,$defCatClass,price,ml FROM ingredients WHERE name = '".$formula['ingredient']."'"));

						$limitIFRA = searchIFRA($ing_q['cas'],$formula['ingredient'],null,$conn,$defCatClass);
						$limit = explode(' - ', $limitIFRA);
					    $limit = $limit['0'];
					  
					    $new_quantity = $formula['quantity']/$mg['total_mg']*$new_conc;
					  	$conc = $new_quantity/$bottle * 100;						
					  	$conc_p = number_format($formula['concentration'] / 100 * $conc, 3);
					 	
						echo'<tr>
                      <td align="center">'.$formula['ingredient'].'</td>
					  <td align="center">'.$ing_q['cas'].'</td>
                      <td align="center">'.$formula['concentration'].'</td>';
					  if($formula['concentration'] == '100'){
						  echo '<td align="center">None</td>';
					  }else{
						  echo '<td data-name="dilutant" class="dilutant" data-type="select" align="center" data-pk="'.$formula['ingredient'].'">'.$formula['dilutant'].'</td>';
					  }
					  if($limit != null){
						 if($limit < $conc_p){
							$IFRA_WARN = 'class="alert-danger"';//VALUE IS TO HIGH AGAINST IFRA
					  	}else{
							$IFRA_WARN = 'class="alert-success"'; //VALUE IS OK
						}
					  }else
					  if($ing_q[$defCatClass] != null){
					  	if($ing_q[$defCatClass] < $conc_p){
							$IFRA_WARN = 'class="alert-danger"'; //VALUE IS TO HIGH AGAINST LOCAL DB
					  	}else{
							$IFRA_WARN = 'class="alert-success"'; //VALUE IS OK
						}
					  }else{
						  $IFRA_WARN = 'class="alert-warning"'; //NO RECORD FOUND
					  }
					  echo'<td align="center">'.number_format($new_quantity, 3).'</td>';
					  echo'<td align="center" '.$IFRA_WARN.'>'.$conc_p.'%</td>';
					  echo '<td align="center">'.utf8_encode($settings['currency']).calcCosts($ing_q['price'],$new_quantity, $formula['concentration'], $ing_q['ml']).'</td>';
					  echo '</tr>';
					$tot[] = calcCosts($ing_q['price'],$new_quantity, $formula['concentration'], $ing_q['ml']);
					$conc_tot[] = $conc_p;
					$new_tot[] = $new_quantity;
				  }
                  ?>
                    </tr>
                    <tr>
                      <td></td>
                      <td></td>
                                            <td></td>

                      <td align="center" class="m-1 text-primary">Sub Total: </td>
                      <td align="center" class="m-1 text-primary"><?php echo number_format(array_sum($new_tot), 3); ?>ml</td>
                      <td align="center" class="m-1 text-primary"><?php echo array_sum($conc_tot);?>%</td>
                      <td colspan="2" align="center" class="m-1 text-primary"><?php echo utf8_encode($settings['currency']).number_format(array_sum($tot),2);?></td>
                    </tr>
                    <tr>
                      <td></td>
                      <td></td>
                                            <td></td>

                      <td align="center" class="m-1 text-primary">Carrier/Solvent: </td>
                      <td align="center" class="m-1 text-primary"><?php echo $carrier; ?>ml</td>
                      <td align="center" class="m-1 text-primary"><?php echo $carrier*100/$bottle;?>%</td>
                      <td colspan="2" align="center" class="m-1 text-primary"><?php $carrier_sub_cost = $carrier_cost['price'] / $carrier_cost['ml'] * $carrier; echo utf8_encode($settings['currency']).number_format($carrier_sub_cost, 2);?></td>
                    </tr>
                    <tr>
                      <td></td>
                      <td></td>
                                            <td></td>

                      <td align="center" class="m-0 text-primary">Bottle:</td>
                      <td align="center" class="m-0 text-primary"><?php echo $bottle_cost['ml'];?>ml</td>
                      <td align="center" class="m-0 text-primary">-</td>
                      <td colspan="2" align="center" class="m-0 text-primary"><?php echo  utf8_encode($settings['currency']).$bottle_cost['price']; ?></td>
                    </tr>
                    <tr>
                      <td></td>
                      <td></td>
                      <td></td>
                      <td align="center" class="m-0 text-primary">Lid:</td>
                      <td align="center" class="m-0 text-primary"><?php echo $lid_cost['style'];?></td>
                      <td align="center" class="m-0 text-primary">-</td>
                      <td colspan="2" align="center" class="m-0 text-primary"><?php echo $settings['currency'].$lid_cost['price'];?></td>
                    </tr>
                    <tr>
                      <td></td>
                      <td></td>
                      <td></td>
                      <td align="center" class="m-0 text-primary">Batch No:</td>
                      <td align="center" class="m-0 text-primary"><?php echo $meta['batchNo'];?></td>
                      <td align="center" class="m-0 text-primary">-</td>
                      <td colspan="2" align="center" class="m-0 text-primary">-</td>
                    </tr>
                    <tr>
                      <td></td>
                      <td></td>
                      <td></td>
                      <td align="center" class="m-0 font-weight-bold text-primary">Total: </td>
                      <td width="15%" align="center" class="m-0 font-weight-bold text-primary"><?php echo number_format(array_sum($new_tot)+ $carrier, 3); ?>ml</td>
                      <td width="15%" align="center" class="m-0 font-weight-bold text-primary"><?php echo $carrier*100/$bottle + array_sum($conc_tot); ?>%</td>
                      <td colspan="2" align="center" class="m-0 font-weight-bold text-primary"><?php echo $settings['currency'].number_format(array_sum($tot)+$lid_cost['price']+$carrier_sub_cost+$bottle_cost['price'],2);?></td>
                    </tr>
                  </tfoot>                                    
                </table> 
                <div>
                <p></p>
                <p>*Values in: <strong class="alert alert-danger">red</strong> exceeds usage level, <strong class="alert alert-warning">yellow</strong> have no usage level set, <strong class="alert alert-success">green</strong> are within usage level</p>
                </div>
            </div>
            
<!-- Modal PRINT-->
<div class="modal fade" id="printBoxLabel" tabindex="-1" role="dialog" aria-labelledby="printBoxLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="printBoxLabel">Print Box Label</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        Copies to print:
          <form action="javascript:printBoxLabel()" method="get" name="form1" target="_self" id="form1">
            <input name="copiesToPrint" type="text" id="copiesToPrint" value="1" />
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <input type="submit" name="button" class="btn btn-primary" id="button" value="Print">
      </div>
     </form>
    </div>
  </div>
</div>

<!-- Modal IFRA CERT-->
<div class="modal fade" id="IFRA" tabindex="-1" role="dialog" aria-labelledby="IFRA" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="IFRA">Generate IFRA Certification</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        Select customer:
          <form action="pages/genIFRAcert.php?fid=<?php echo $meta['fid'];?>&conc=<?php echo $type; ?>&bottle=<?php echo $bottle;?>" method="post" name="form1" target="_blank" id="form1">
            <select class="form-control" name="customer" id="customer">
            <?php
				$res = mysqli_query($conn, "SELECT id, name FROM customers ORDER BY name ASC");
				while ($q = mysqli_fetch_array($res)){
				echo '<option value="'.$q['id'].'">'.$q['name'].'</option>';
			}
			?>
            </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <input type="submit" name="button" class="btn btn-primary" id="button" value="Generate">
      </div>
     </form>
    </div>
  </div>
</div>
            <?php 
			}else{ 
				if(mysqli_num_rows(mysqli_query($conn, "SELECT id FROM formulasMetaData"))== 0){
					echo '<div class="alert alert-info alert-dismissible"><strong>INFO: </strong> You need to <a href="?do=addFormula">create</a> at least one formula first.</div>';
					return;
				}
				if(mysqli_num_rows(mysqli_query($conn, "SELECT id FROM bottles"))== 0){
					echo '<div class="alert alert-info alert-dismissible"><strong>INFO: </strong> You need to <a href="?do=addBottle">add</a> at least one bottle in your inventory first.</div>';
					return;
				}
				if(mysqli_num_rows(mysqli_query($conn, "SELECT id FROM ingredients WHERE type = 'Carrier' OR type = 'Solvent'"))== 0){
					echo '<div class="alert alert-info alert-dismissible"><strong>INFO: </strong> You need to <a href="?do=ingredients">add</a> at least one solvent or carrier first.</div>';
					return;
				}
			?>
           <form action="?do=genFinishedProduct&generate=1" method="post" enctype="multipart/form-data" target="_self">
           
           <table width="100%" border="0">
  <tr>
    <td width="9%">Formula:</td>
    <td width="24%">
    <select name="formula" id="formula" class="form-control selectpicker" data-live-search="true">
     <?php
		$sql = mysqli_query($conn, "SELECT fid,name,product_name FROM formulasMetaData WHERE product_name IS NOT NULL ORDER BY name ASC");
		while ($formula = mysqli_fetch_array($sql)){
			echo '<option value="'.$formula['name'].'">'.$formula['name'].' ('.$formula['product_name'].')</option>';
		}
	  ?>
     </select>
   </td>
    <td width="67%">&nbsp;</td>
  </tr>
  <tr>
    <td>Concentration:</td>
    <td>
        <select name="type" id="type" class="form-control selectpicker" data-live-search="true">
     <?php
	 		echo '<option value="'.$settings['Parfum'].'">Parfum ('.$settings['Parfum'].'%)</option>';
			echo '<option value="'.$settings['EDP'].'">EDP ('.$settings['EDP'].'%)</option>';
			echo '<option value="'.$settings['EDT'].'">EDT ('.$settings['EDT'].'%)</option>';
			echo '<option value="'.$settings['EDC'].'">EDC ('.$settings['EDC'].'%)</option>';
			echo '<option value="100">Concentrated (100%)</option>';

	  ?>
     </select>
    </td>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td>Category Class:</td>
    <td><select name="defCatClass" id="defCatClass" class="form-control selectpicker" data-live-search="true">
			  <option value="cat1" <?php if($settings['defCatClass']=="cat1") echo 'selected="selected"'; ?> >Cat 1</option>
			  <option value="cat2" <?php if($settings['defCatClass']=="cat2") echo 'selected="selected"'; ?> >Cat 2</option>
			  <option value="cat3" <?php if($settings['defCatClass']=="cat3") echo 'selected="selected"'; ?> >Cat 3</option>
              <option value="cat4" <?php if($settings['defCatClass']=="cat4") echo 'selected="selected"'; ?> >Cat 4</option>
			  <option value="cat5A" <?php if($settings['defCatClass']=="cat5A") echo 'selected="selected"'; ?> >Cat 5A</option>
			  <option value="cat5B" <?php if($settings['defCatClass']=="cat5B") echo 'selected="selected"'; ?> >Cat 5B</option>
			  <option value="cat5C" <?php if($settings['defCatClass']=="cat5C") echo 'selected="selected"'; ?> >Cat 5C</option>
			  <option value="cat5D" <?php if($settings['defCatClass']=="cat5D") echo 'selected="selected"'; ?> >Cat 5D</option>
			  <option value="cat6" <?php if($settings['defCatClass']=="cat6") echo 'selected="selected"'; ?> >Cat 6</option>
			  <option value="cat7A" <?php if($settings['defCatClass']=="cat7A") echo 'selected="selected"'; ?> >Cat 7A</option>
			  <option value="cat7B" <?php if($settings['defCatClass']=="cat7B") echo 'selected="selected"'; ?> >Cat 7B</option>
			  <option value="cat8" <?php if($settings['defCatClass']=="cat8") echo 'selected="selected"'; ?> >Cat 8</option>
			  <option value="cat9" <?php if($settings['defCatClass']=="cat9") echo 'selected="selected"'; ?> >Cat 9</option>
			  <option value="cat10A" <?php if($settings['defCatClass']=="cat10A") echo 'selected="selected"'; ?> >Cat 10A</option>
			  <option value="cat10B" <?php if($settings['defCatClass']=="cat10B") echo 'selected="selected"'; ?> >Cat 10B</option>
			  <option value="cat11A" <?php if($settings['defCatClass']=="cat11A") echo 'selected="selected"'; ?> >Cat 11A</option>
			  <option value="cat11B" <?php if($settings['defCatClass']=="cat11B") echo 'selected="selected"'; ?> >Cat 11B</option>
			  <option value="cat12" <?php if($settings['defCatClass']=="cat12") echo 'selected="selected"'; ?> >Cat 12</option>
            </select></td>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td>Batch ID:</td>
    <td><select name="batchID" id="batchID" class="form-control selectpicker" data-live-search="false">
      <option value="0">Do Not Generate</option>
      <option value="1">Generate</option>
    </select></td>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td>Bottle:</td>
    <td>    
    <select name="bottle" id="bottle" class="form-control selectpicker" data-live-search="true">
     <?php
		$sql = mysqli_query($conn, "SELECT id,name,ml FROM bottles ORDER BY ml DESC");
		while ($bottle = mysqli_fetch_array($sql)){
			echo '<option value="'.$bottle['id'].'">'.$bottle['name'].' ('.$bottle['ml'].'ml)</option>';
		}
	  ?>
     </select>
     </td>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td>Carrier:</td>
    <td>
    <select name="carrier" id="carrier" class="form-control selectpicker" data-live-search="true">
      <?php
		$sql = mysqli_query($conn, "SELECT name,id FROM ingredients WHERE type = 'Carrier' OR type = 'Solvent' ORDER BY name ASC");
		while ($carrier = mysqli_fetch_array($sql)){
			echo '<option value="'.$carrier['id'].'">'.$carrier['name'].'</option>';
		}
	  ?>
    </select>
    </td>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td>Bottle Lid:</td>
    <td><select name="lid" id="lid" class="form-control selectpicker" data-live-search="true">
      <option value="0" selected="selected">None</option>
      <?php
		$sql = mysqli_query($conn, "SELECT style,id FROM lids ORDER BY style ASC");
		while ($lid = mysqli_fetch_array($sql)){
			echo '<option value="'.$lid['id'].'">'.$lid['style'].'</option>';
		}
	  ?>
    </select></td>
    <td>&nbsp;</td>
  </tr>
  <tr>
    <td>&nbsp;</td>
    <td colspan="2">&nbsp;</td>
  </tr>
  <tr>
    <td><input type="submit" name="button" class="btn btn-info" id="button" value="Generate"></td>
    <td colspan="2">&nbsp;</td>
  </tr>
</table>
           </form>          
            <?php } ?>
           </div>
        </div>
      </div>
   </div>
  </div>
<script type="text/javascript" language="javascript" >


$('#pdf').on('click',function(){
  $("#formula").tableHTMLExport({
	type:'pdf',
	filename:'<?php echo $f_name; ?>.pdf',
	orientation: 'p',
	trimContent: true,
    quoteFields: true,
	ignoreColumns: '.noexport',
  	ignoreRows: '.noexport',
	htmlContent: true,
	maintitle: '<?php echo $f_name; ?>',
	product: '<?php echo trim($product).' '.trim($ver);?>'

});
 
})

</script>
