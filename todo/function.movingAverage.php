<?php
# ěšččřžýáýů

# Calculate Moving Average (http://en.wikipedia.org/wiki/Moving_average) from given data and subset size.
# if samecount is true, number of elements returned is same as in data
# if samecount is false, number of elements returned is count(data)/subsetsize
function movingAverage ($data, $subsetsize=5, $samecount=true) {
	if (!is_array ($data))
		return (null);
	
	$subsetsize = (int) $subsetsize;
	if ($subsetsize<1 || count ($data)<$subsetsize)
		return ($data);

	# change tu numeric index array
	$nidata = array_values ($data);

	$prev = 0;
	$output = array ();

	# first element
	$output[0] = $prev = array_sum (array_slice($nidata, 0, $subsetsize))/$subsetsize;
	$i=0;
	for ($i=1; $i < count ($nidata); $i++) {
		$output[$i] = $prev = $prev - $nidata[$i-1]/$subsetsize + $nidata[$i+$subsetsize-1]/$subsetsize;
		}

	return ($output);
	}
?>