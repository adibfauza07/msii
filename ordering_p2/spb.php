<?php 
    //header("Content-type: application/vnd-ms-excel");
    //header("Content-Disposition: attachment; filename=resume_mcu.xls"); 
?>
<html>
<head>
	<title>Material/Part Slip</title>
	<link rel="shortcut icon" href="<?=base_url()?>assets/images/logo.png"> 
	<link href="<?=base_url('assets/css/bootstrap.css');?>" rel="stylesheet" type="text/css" media="all"/>
	<style>
	@media print
	{    
		.no-print, .no-print *
		{
			display: none !important;
		}
	}
	@media screen {
		.invoice{
			margin-left:5px;
			margin-right:5px;
		}
		
		
	}
	</style>
	<style>
		.table >thead tr th{
			padding:2px;
			font-size:11px;
		}
		.table >tbody tr td{
			padding:2px;
			font-size:10px;
		}
		.kecilsekali{
			font-size:10px;
			padding-right:2px;
		}
		.kecilbanget{
			font-size:8px;
			padding-right:2px;
		}
		.kecil{
			font-size:12px;
			padding-right:2px;
		}
		.tengah{
			text-align:center;
		}
		.kanan{
			text-align:right;
		}
		.besar{
			font-size:14px;
			font-weight:bold;
			color:#D15B47;
			padding-right:2px;
		}
		.gkiri{
			border-left:1px solid;
		}
		.gatas{
			border-top:1px solid;
		}
		.gkanan{
			border-right:1px solid;
		}
		.gbawah{
			border-bottom:1px solid;
		}
		
</style>
</head>
<body>

<section class="invoice" style="margin-top:2px;padding:10px;text-align:center;">
	
	<div class="col-lg-12">
		<div class="row" style="min-height:200px;">
		<center>
				<table width="750px" border="0">
					<tr>
						<td style="width:50%;">
							<b style="font-size:18px;"><?php echo $this->apps->get_instansi()->nama_instansi ?> PLANT 1</b><br>
							<small class="kecil">PPIC DEPARTEMENT </small>
						</td>
						<td class="kecil " style="text-align:right;pdding-right:20px;"">&nbsp;TIME: <?=date('H:i:s',strtotime($header->TRANS_TIME))  ?>
						</td>
					</tr>
					<tr>
						<td colspan="2" style="padding:0px;text-align:center;"><b style="font-size:16px;">MATERIAL/PART SLIP</b></td>
					</tr>
					
				</table>
				<br>
				<table width="750px" border="0">
					<tr>
						<th class="kecil " style="width:20%;">NOMOR</th>
						<th class="kecil " style="width:1%;">:</th>
						<th class="kecil " style="width:29%;"><?=$header->TRAN_DOC?></th>
						<th class="kecil " style="width:20%;">&nbsp;</th>
						<th class="kecil " style="width:1%;">&nbsp;</th>
						<th class="kecil " style="width:50%;vertical-align:top;">&nbsp;</th>
						
					</tr>
					<tr>
						<th class="kecil " style="">DATE</th>
						<th class="kecil" style="">:</th>
						<th class="kecil " style=""><?=date('d-M-Y',strtotime($header->TRAN_ADATE))?></th>
						<th class="kecil " style="">FROM</th>
						<th class="kecil " style="width:1%;">:</th>
						<th class="kecil " style="width:50%;vertical-align:top;"><?=$header->TRTY_DESC?></th>
					</tr>
					
				</table>
				<br>
				<table width="750px" border="1">
					<thead>
					<tr>
						<th class="kecil " style="width:5%">NO</th>
						<th class="kecil " style="width:20%">WO NO</th>
						<th class="kecil " style="width:10%">ITEM CODE</th>
						<th class="kecil " style="width:25%">MATERIAL NAME</th>
						<th class="kecil " style="width:5%">UNIT</th>
						<th class="kecil kanan" style="width:20%">QUANTITY</th>
						<th class="kecil tengah" style="width:30%">REMARK</th>
					</tr>
					</thead>
					<?php
					if($sql>0){
						$no=1;
						$sub=0;
						foreach($detail->result() as $key){
							echo '<tr>
									<td class="kecil">'.$no.'</td>
									<td></td>
									<td class="kecil">'.$key->ITEM_CODE.'</td>
									<td class="kecil">'.$key->ITEM_NAME.'</td>
									<td class="kecil">'.$key->ITEM_UNIT.'</td>
									<td class="kecil kanan">'.number_format($key->IT_QTY,2).'</td>
									<td class="kecil"></td>
							</tr>';
							$no++;
							$sub=$sub+$key->IT_QTY;
						}
						echo '<tr>
									<td colspan="5" class="kecil kanan">TOTAL</td>
									<td class="kecil kanan">'.number_format($sub,2).'</td>
									<td class="kecil"></td>
							</tr>';
					}else{
						echo '<tr>
								<td colspan="7" class="kecil tengah">BELUM ADA DETAIL</td>
							</tr>';
					}
					?>
					
				</table>
				<br>
				
				
				<table width="750px" border="0">
					<tr>
						<td colspan="5" style="" class="kecil">FM.CO.01-36(Revisi2:Tgl.10.Des.2019)</td>
					</tr>
					<tr>
						<td style="width:20%;" class="kecil tengah">DELIVERED</td>
						<td style="width:20%;" class="kecil tengah">&nbsp;</td>
						<td style="width:20%;" class="kecil tengah">&nbsp;</td>
						<td style="width:20%;" class="kecil tengah">&nbsp;</td>
						<td style="width:20%;" class="kecil tengah">RECEIVED</td>
					</tr>
					<tr>
						<td style="" class="kecil tengah"></td>
						<td style="" class="kecil tengah"></td>
						<td style="" class="kecil tengah"></td>
						<td style="" class="kecil tengah"></td>
						<td style="height:50px;" class="kecil tengah"></td>
					</tr>
					<tr>
						<td style="" class="kecil tengah">(..............................)</td>
						<td style="" class="kecil tengah"></td>
						<td style="" class="kecil tengah">&nbsp;</td>
						<td style="" class="kecil tengah"></td>
						<td style="" class="kecil tengah">(..............................)</td>
					</tr>
					
				</table>
		</center>
		</div>
<!-- this row will not appear when printing -->
		<div class="row">
			  <div class="row no-print">
				<hr style="padding:0px;margin-top:5px;margin-bottom:5px;">
				<div class="col-sm-12">
					<div class="pull-right" style="padding-right:20px;">
						<a href="#" class="btn btn-default cetak"><i class="fa fa-print"></i> Print</a> 
						<a href="#" class="btn btn-default tutup"><i class="fa fa-print"></i> Close</a>
					</div>
				</div>
			  </div>
		</div>
	</div>
	
</section>

</body>
</html>


<script src="<?=base_url('assets/js/jQuery-2.1.4.min.js');?>"></script>
<script>
$(document).ready(function(){
	$('body').on('click','.cetak',function(e){
		window.print();
		window.close();
	});
	$('body').on('click','.tutup',function(e){
		window.close();
	});
});
</script>
