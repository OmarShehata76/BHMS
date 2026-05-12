<?php
$txt1 ="Hi ";
$txt2 ="there <br>";
$x =5;
$y =4;
echo$txt1 . $txt2 ;
echo$x + $y;?>

<br>---------------------</br>

<?php
$n1=1;
$n2=2;
$n3=5;
$sum= $n1 +$n2 +$n3;
echo $sum ,"<br>";
echo $sum/3 ,"<br>";
echo $n1**2 + $n2**2 +$n3**2;
?>

<br>---------------------</br>

<?php
$n1=3;
$n2='3';
if ($n1==$n2){
echo 'equal';
}
else {
echo 'not equal';
}
echo '<br>';
if ($n1===$n2){
echo 'equal';
}
else {
echo 'not equal';
}
?>

<br>---------------------</br>


<?php

$cars = array ( 
    array("Volvo",22,18), 
    array("BMW",15,13), 
    array("Saab",5,2), 
    array("Land Rover",17,15));

echo $cars[0][0].": In stock: ".$cars[0][1].", sold: ".$cars[0][2].".<br>";

echo $cars[1][0].": In stock: ".$cars[1][1].", sold: ".$cars[1][2].".<br>";

echo $cars[3][0].": In stock: ".$cars[3][1].", sold: ".$cars[3][2].".<br>";
?>

<br>---------------------</br>


<?php

for ($row = 0; $row < 4; $row++) {
echo "<p><b>Row number $row</b></p>";
echo "<ul>";
for ($col = 0; $col < 3; $col++) {
echo "<li>".$cars[$row][$col]."</li>";
}
echo "</ul>";
}
?>

<?php


$colors =array("red","green","blue","yellow");foreach($colors as $value) {echo"$value <br>";}

$count=0;
foreach ($cars as $car) {
echo "<p><b>Row number $count</b></p>";
echo "<ul>";
foreach ($car as $item) {
echo "<li>".$item."</li>";
}
$count++;
echo "</ul>";
}
?>

<br>---------------------</br>


<?php

$nums=array(10,2,43,14,-5);
print_r($nums);
echo "<br>";
array_pop($nums);
print_r($nums);
echo "<br>";
array_push($nums,11);
print_r($nums);
echo "<br>";
echo 'search for the index of 14 :', array_search(14,$nums),'<br>';
if(in_array(10,$nums))
echo 'found <br>';
else
echo 'not found';
echo "<br>";
?>

<br>---------------------</br>


<?php

$cars = array("Volvo", "BMW", "Toyota");
$age = array("Peter"=>"35", "Ben"=>"37", "Joe"=>"43");
$nums=array(10,2,43,14,-5);
sort($cars);
print_r ($cars);
echo '<br>';
rsort ($nums);
print_r ($nums);
echo '<br>';
krsort($age);
print_r($age);
echo '<br>';
?>


