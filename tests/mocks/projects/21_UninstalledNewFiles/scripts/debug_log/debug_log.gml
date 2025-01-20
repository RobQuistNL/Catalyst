///debug_log(message);
function debug_log(argument0) {
	show_debug_message("[" + string_prepend(current_hour, "0", 2) + ":" +string_prepend(current_minute, "0", 2)+":"+string_prepend(current_second, "0", 2)+"] " + object_get_name(object_index) + ": " + string(argument0));


}
