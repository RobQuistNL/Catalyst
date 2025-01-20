/// @description round_whole(number, round);
/// @function round_whole
/// @param number
/// @param  round
function round_whole(argument0, argument1) {
	/**
	 * Round number on big numbers 
	 * ex: round_whole(1234, 10) = 1230
	 * @return int
	 */
	return round(round((argument0/argument1))*argument1);


}
