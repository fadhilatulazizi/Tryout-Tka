<?php 
require_once dirname(__DIR__, 2) . '/koneksiku.php';
session_start();
IF(ISSET($_SESSION['nama'])){
?>

<?php
IF(ISSET($_POST['jawab'])){
	$id 	= $_SESSION['id'];
	$jwb	= $_POST['jawaban'];
	$no_soal ='10';
	$sql 	= "SELECT * FROM pg_tb WHERE id='$id' ";
	$insert	= mysqli_query($db, "UPDATE pg_tb SET j$no_soal='$jwb' WHERE id='$id'");
	if(!$insert){
	echo "<script>alert('Gagal');history.go(-1);</script>";
	} else{
	echo "<script>alert('Data tersimpan');history.go(-1);</script>";
	}
}
?>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
		<link rel="stylesheet" href="../css/style4.css" type="text/css" />		
<title>Show Case</title>

</head>
<body>
<?php
    $no_soalku  = '10';
	$id 		= $_SESSION['id'];
	$sql2 		= "SELECT * FROM pg_tb WHERE id='$id' ";
	$result2 	= mysqli_query($db,$sql2);
	$data2   	= mysqli_fetch_array($result2);
	$a			= 'j';
	$no			= $a.$no_soalku;
	
	$sql3 	= "SELECT * FROM account WHERE id='$id' ";
	$result3 = mysqli_query($db,$sql3);
	$data3   = mysqli_fetch_array($result3);
	
	$no_1	= $data3['a1'];
	$no_2	= $data3['a2'];
	$no_3	= $data3['a3'];
	$no_4	= $data3['a4'];
	$no_5	= $data3['a5'];
	$no_6	= $data3['a6'];
	$no_7	= $data3['a7'];
	$no_8	= $data3['a8'];
	$no_9	= $data3['a9'];
	$no_10	= $data3['a10'];
	$no_11	= $data3['a11'];
	$no_12	= $data3['a12'];
	$no_13	= $data3['a13'];
	$no_14	= $data3['a14'];
	$no_15	= $data3['a15'];
	$no_16	= $data3['a16'];
	$no_17	= $data3['a17'];
	$no_18	= $data3['a18'];
	$no_19	= $data3['a19'];
	$no_20	= $data3['a20'];
	$no_21	= $data3['a21'];
	$no_22	= $data3['a22'];
	$no_23	= $data3['a23'];
	$no_24	= $data3['a24'];
	$no_25	= $data3['a25'];
	$no_26	= $data3['a26'];
	$no_27	= $data3['a27'];
	$no_28	= $data3['a28'];
	$no_29	= $data3['a29'];
	$no_30	= $data3['a30'];
	$no_31	= $data3['a31'];
	$no_32	= $data3['a32'];
	$no_33	= $data3['a33'];
	$no_34	= $data3['a34'];
	$no_35	= $data3['a35'];
	$no_36	= $data3['a36'];
	$no_37	= $data3['a37'];
	$no_38	= $data3['a38'];
	$no_39	= $data3['a39'];
	$no_40	= $data3['a40'];
	$no_41	= $data3['a41'];
	$no_42	= $data3['a42'];
	$no_43	= $data3['a43'];
	$no_44	= $data3['a44'];
	$no_45	= $data3['a45'];
	$no_46	= $data3['a46'];
	$no_47	= $data3['a47'];
	$no_48	= $data3['a48'];
	$no_49	= $data3['a49'];
	$no_50	= $data3['a50'];
	
	$nomor	= array(
		"$no_1" =>"1","$no_2" =>"2","$no_3" =>"3","$no_4" =>"4","$no_5" =>"5",
		"$no_6" =>"6","$no_7" =>"7","$no_8" =>"8","$no_9" =>"9","$no_10" =>"10",
		"$no_11" =>"11","$no_12" =>"12","$no_13" =>"13","$no_14" =>"14","$no_15" =>"15",
		"$no_16" =>"16","$no_17" =>"17","$no_18" =>"18","$no_19" =>"19","$no_20" =>"20",
		"$no_21" =>"21","$no_22" =>"22","$no_23" =>"23","$no_24" =>"24","$no_25" =>"25",
		"$no_26" =>"26","$no_27" =>"27","$no_28" =>"28","$no_29" =>"29","$no_30" =>"30",
		"$no_31" =>"31","$no_32" =>"32","$no_33" =>"33","$no_34" =>"34","$no_35" =>"35",
		"$no_36" =>"36","$no_37" =>"37","$no_38" =>"38","$no_39" =>"39","$no_40" =>"40",
		"$no_41" =>"41","$no_42" =>"42","$no_43" =>"43","$no_44" =>"44","$no_45" =>"45",
		"$no_46" =>"46","$no_47" =>"47","$no_48" =>"48","$no_49" =>"49","$no_50" =>"50"
	)
?>
<div > 
  <table width="100%" border="0" class="text_normal">
    <tr>
      <td valign="top" width= "10"><div class="nomor">
	<?php echo $nomor['10'] ?>
	</div></td>
      <td><p>Cermatilah teks puisi rakyat berikut!<strong> </strong></p>
        <p>Pagi hari pergi ke pasar<br>
          Membeli sayur bersama adik<br>
          Janganlah malas untuk belajar<br>
          Agar masa depanmu lebih baik</p>
        <p>Pesan yang disampaikan pantun tesebut adalah&hellip;</p>
        <ol type="A">
          <li>Kita sebaiknya membantu adik membeli sayur di  pasar.</li>
          <li>Belajar rajin akan membuat masa depan lebih  baik.</li>
          <li>Masa depan sangat perlu dipikirkan sejak kecil.</li>
          <li>Malas belajar adalah  kunci kesuksesan masa depan. </li>
        </ol>
      </td>
    </tr>
  </table>

<form name="form1" method="post" action="">
 <div align="center">
    <p>
	  <label class="text_normal">
	  <input name="jawaban" type="radio" value="A" <?php if($data2[$no]=='A')echo 'checked'?>>
	  A </label>
	  	  <label class="text_normal">
	  <input name="jawaban" type="radio" value="B" <?php if($data2[$no]=='B')echo 'checked'?>>
	  B </label>
	  	  <label class="text_normal">
	  <input name="jawaban" type="radio" value="C" <?php if($data2[$no]=='C')echo 'checked'?>>
	  C </label>
	  	  <label class="text_normal">
	  <input name="jawaban" type="radio" value="D" <?php if($data2[$no]=='D')echo 'checked'?>> 
	  D</label>
    </p>
    <p>
      <input type="submit" name="jawab" value="Kirim Jawaban">
    </p>
</div>
</form>
</body>
</html>
<?php 

}else{
	echo "<script language=\"javascript\">alert(\"Verifikasi username dan password Anda\");document.location.href='../user_login/user_login.php';</script>";	
}
?>
