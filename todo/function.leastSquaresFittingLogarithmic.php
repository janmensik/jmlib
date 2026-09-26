<?php
# ěšččřžýáýů

# ...................................................................
# http://stackoverflow.com/questions/2768885/how-can-i-calculate-a-trend-line-in-php
function leastSquaresFittingLogarithmic ($data) {
	if (!is_array ($data))
		return (null);
	$i=1;
	foreach ($data as $key=>$value) {
		$X[] = $i;
		$Y[] = $value;
		$i++;
		}
	
	$x=5;
	$y=100;

	//print_r ($X);echo ('<hr />');print_r ($Y);
	// Now convert to log-scale for X
  $logX = array_map('log', $X);

  // Now estimate $a and $b using equations from Math World
  $n = count($X);
  $square = create_function('$x', 'return pow($x,2);');
  $x_squared = array_sum(array_map($square, $logX));
  $y_squared = array_sum(array_map($square, $Y));
  $xy = array_sum(array_map(create_function('$x,$y', 'return $x*$y;'), $logX, $Y));

  $bFit = ($n * $xy - array_sum($Y) * array_sum($logX)) /
          ($n * $x_squared - pow(array_sum($logX), 2));

  $aFit = (array_sum($Y) - $bFit * array_sum($logX)) / $n;

	$Yfit = array();
  foreach($X as $x) {
    $Yfit[] = $aFit + $bFit * log($x);
		}
	return ($Yfit);
	}
?>