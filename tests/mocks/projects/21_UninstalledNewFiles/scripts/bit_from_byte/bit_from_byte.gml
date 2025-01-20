///@param byte 
///@param nth_bit
function bit_from_byte(argument0, argument1) {
	return (argument0 & ( 1 << argument1 )) >> argument1;


}
