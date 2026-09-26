<?php
# ěšččřžýáýů

# ...................................................................
function oneFromArray ($data, $key) {
	if (!is_array ($data) || !$key)
		return (null);
	
	foreach ($data as $k=>$value) {
		$output[$k] = $value[$key]; 
		}
	
	return ($output);
	}
?>