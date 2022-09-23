jQuery(window).on("load", function () {
	init();
});
jQuery(document).ready(function () {
	jQuery("select#woocommerce_easyparcel_sender_country").change(function (e, trigger = true) {
		init();
	});

	jQuery("#woocommerce_easyparcel_enabled").change(function () {
		init();
	});

	jQuery("#woocommerce_easyparcel_sender_contact_number").change(function () {
		var a = jQuery("#woocommerce_easyparcel_sender_contact_number").val();
		var country = jQuery("select#woocommerce_easyparcel_sender_country").val();
		var filter = /^(\+?6?01)[02-46-9]-*[0-9]{7}$|^(\+?6?01)[1]-*[0-9]{8}$/;
		var example = "";
		if (country == "MY") {
			filter = /^(\+?6?01)[02-46-9]-*[0-9]{7}$|^(\+?6?01)[1]-*[0-9]{8}$/;
			example = "60164433221";
		} else if (country == "SG") {
			filter = /\65(6|8|9)[0-9]{7}$/;
			example = "6598765432";
		}

		if (filter.test(a)) {
		} else {
			alert("not an valid mobile format. Example: " + example);
		}
	});

	jQuery("#woocommerce_easyparcel_sender_alt_contact_number").change(function () {
		var a = jQuery("#woocommerce_easyparcel_sender_alt_contact_number").val();
		var country = jQuery("select#woocommerce_easyparcel_sender_country").val();
		var filter = /^(\+?6?01)[02-46-9]-*[0-9]{7}$|^(\+?6?01)[1]-*[0-9]{8}$/;
		var example = "";
		if (country == "MY") {
			filter = /^(\+?6?01)[02-46-9]-*[0-9]{7}$|^(\+?6?01)[1]-*[0-9]{8}$/;
			example = "60164433221";
		} else if (country == "SG") {
			filter = /\65(6|8|9)[0-9]{7}$/;
			example = "6598765432";
		}

		if (filter.test(a)) {
		} else {
			alert("not an valid mobile format. Example: " + example);
		}
	});
});

function init() {
	var selected = jQuery("select#woocommerce_easyparcel_sender_country").find(":selected").val();
	if (selected != "NONE" && jQuery("#woocommerce_easyparcel_enabled").is(":checked")) {
		showdetails(selected);
		change_courier(selected);
		change_state(selected);
		if (obj.sender_state != null) {
			jQuery("select#woocommerce_easyparcel_sender_state").val(obj.sender_state);
		}
		if (obj.courier_service != null) {
			jQuery("select#woocommerce_easyparcel_courier_service").val(obj.courier_service);
		}
	} else {
		hidedetails(selected);
	}
}

function hidedetails(country) {
	jQuery("#woocommerce_easyparcel_sender_detail").hide();
	jQuery("#woocommerce_easyparcel_sender_name").closest("tr").hide();
	jQuery("#woocommerce_easyparcel_sender_contact_number").closest("tr").hide();
	jQuery("#woocommerce_easyparcel_sender_alt_contact_number").closest("tr").hide();
	jQuery("#woocommerce_easyparcel_sender_company_name").closest("tr").hide();
	jQuery("#woocommerce_easyparcel_easyparcel_email").closest("tr").hide();
	jQuery("#woocommerce_easyparcel_sender_address_1").closest("tr").hide();
	jQuery("#woocommerce_easyparcel_sender_address_2").closest("tr").hide();
	jQuery("#woocommerce_easyparcel_sender_city").closest("tr").hide();
	jQuery("#woocommerce_easyparcel_sender_postcode").closest("tr").hide();
	jQuery("#woocommerce_easyparcel_sender_state").closest("tr").hide();
	jQuery("#woocommerce_easyparcel_integration_id").closest("tr").hide();
	jQuery("#woocommerce_easyparcel_courier_service").closest("tr").hide();
	// jQuery("#woocommerce_easyparcel_enabled").closest("tr").hide();

	jQuery("#woocommerce_easyparcel_order_status_update_setting").hide();
	jQuery("#woocommerce_easyparcel_order_status_update_option").closest("tr").hide();

	jQuery("#woocommerce_easyparcel_addon_service_setting").hide();
	jQuery("#woocommerce_easyparcel_addon_email_option").closest("tr").hide();
	jQuery("#woocommerce_easyparcel_addon_sms_option").closest("tr").hide();
}

function showdetails(country) {
	jQuery("#woocommerce_easyparcel_sender_detail").show();
	jQuery("#woocommerce_easyparcel_sender_name").closest("tr").show();
	jQuery("#woocommerce_easyparcel_sender_contact_number").closest("tr").show();
	jQuery("#woocommerce_easyparcel_sender_alt_contact_number").closest("tr").show();
	jQuery("#woocommerce_easyparcel_sender_company_name").closest("tr").show();
	jQuery("#woocommerce_easyparcel_easyparcel_email").closest("tr").show();
	jQuery("#woocommerce_easyparcel_sender_address_1").closest("tr").show();
	jQuery("#woocommerce_easyparcel_sender_address_2").closest("tr").show();
	jQuery("#woocommerce_easyparcel_sender_city").closest("tr").show();
	jQuery("#woocommerce_easyparcel_sender_postcode").closest("tr").show();
	jQuery("#woocommerce_easyparcel_sender_state").closest("tr").show();
	jQuery("#woocommerce_easyparcel_integration_id").closest("tr").show();
	jQuery("#woocommerce_easyparcel_courier_service").closest("tr").show();
	// jQuery("#woocommerce_easyparcel_enabled").closest("tr").show();

	jQuery("#woocommerce_easyparcel_order_status_update_setting").show();
	jQuery("#woocommerce_easyparcel_order_status_update_option").closest("tr").show();

	if (country == "MY") {
		jQuery("#woocommerce_easyparcel_addon_service_setting").show();
		jQuery("#woocommerce_easyparcel_addon_email_option").closest("tr").show();
		jQuery("#woocommerce_easyparcel_addon_sms_option").closest("tr").show();
	} else {
		jQuery("#woocommerce_easyparcel_addon_service_setting").hide();
		jQuery("#woocommerce_easyparcel_addon_email_option").closest("tr").hide();
		jQuery("#woocommerce_easyparcel_addon_sms_option").closest("tr").hide();
		jQuery("#woocommerce_easyparcel_addon_email_option").prop("checked", false);
		jQuery("#woocommerce_easyparcel_addon_sms_option").prop("checked", false);
	}
}

function clearField() {
	jQuery("#woocommerce_easyparcel_sender_contact_number").val("");
	jQuery("#woocommerce_easyparcel_sender_alt_contact_number").val("");
	jQuery("#woocommerce_easyparcel_easyparcel_email").val("");
	jQuery("#woocommerce_easyparcel_sender_address_1").val("");
	jQuery("#woocommerce_easyparcel_sender_address_2").val("");
	jQuery("#woocommerce_easyparcel_sender_city").val("");
	jQuery("#woocommerce_easyparcel_sender_state").val("");
	jQuery("#woocommerce_easyparcel_sender_postcode").val("");
	jQuery("#woocommerce_easyparcel_integration_id").val("");
	jQuery("select#woocommerce_easyparcel_courier_service").val("cheaper");
}

function change_courier($country) {
	var courier = [];
	var option = "";
	if ($country == "SG") {
		courier["EP-CS0GU"] = "Ninjavan (Collect)";
		courier["EP-CS0MU"] = "MRight";
		courier["EP-CS04G"] = "Janio";
		courier["EP-CS0Q6"] = "J&T Express"; // archived service_id 309
		courier["EP-CS0RY"] = "UrbanFox";
		courier["EP-CS0RG"] = "Qxpress";
		courier["EP-CS0RQ"] = "Mystery Saver";
		courier["EP-CS0EK"] = "Singpost";
		courier["EP-CS0NO"] = "Aramex";
		courier["EP-CS0GH"] = "Airpak Express";
		courier["EP-CS0WO"] = "XDel";
		courier["all"] = "All Couriers";
		courier["cheaper"] = "Cheapest Courier(s)";
	} else {
		courier["EP-CR0DP"] = "J&T Express";
		courier["EP-CR05"] = "Skynet";
		courier["EP-CR0AL"] = "Teleport (Support only EM)";
		courier["EP-CR0D"] = "Airpak";
		courier["EP-CR0J"] = "Ultimate Consolidators (Support only EM)";
		courier["EP-CR0W"] = "SnT Global";
		courier["EP-CR03"] = "Aramex";
		courier["EP-CR0C"] = "DHL eCommerce";
		courier["EP-CR0Z"] = "CJ Logistics";
		courier["EP-CR0O"] = "Pgeon Delivery";
		courier["EP-CR0M"] = "Nationwide Express Courier Service Berhad";
		courier["EP-CR0A"] = "Poslaju National Courier";
		courier["all"] = "All Couriers";
		courier["cheaper"] = "Cheapest Courier(s)";
	}

	for (const key in courier) {
		if (obj.sender_state == key) {
			option += `<option value="${key}" selected='selected'>${courier[key]}</option>`;
		} else {
			option += `<option value="${key}" >${courier[key]}</option>`;
		}
	}
	jQuery("select#woocommerce_easyparcel_courier_service").empty();
	jQuery("select#woocommerce_easyparcel_courier_service").append(option);
}

function change_state($country) {
	var state = "";
	if ($country == "SG") {
		jQuery("select#woocommerce_easyparcel_sender_state").closest("td").siblings("th").html('<font color="red">*</font>Zone');
		state = '<option value="central">CENTRAL</option><option value="east">EAST</option><option value="north">NORTH</option><option value="northeast">NORTHEAST</option><option value="west">WEST</option>';
	} else {
		jQuery("select#woocommerce_easyparcel_sender_state").closest("td").siblings("th").html('<font color="red">*</font>State');
		state =
			'<option value="jhr">Johor</option><option value="kdh">Kedah</option><option value="ktn">Kelantan</option><option value="kul">Kuala Lumpur</option><option value="lbn">Labuan</option><option value="mlk">Malacca</option><option value="nsn">Negeri Sembilan</option><option value="phg">Pahang</option><option value="prk">Perak</option><option value="pls">Perlis</option><option value="png">Penang</option><option value="sbh">Sabah</option><option value="srw">Sarawak</option><option value="sgr">Selangor</option><option value="trg">Terengganu</option><option value="pjy">Putra Jaya</option>';
	}
	jQuery("select#woocommerce_easyparcel_sender_state").empty();
	jQuery("select#woocommerce_easyparcel_sender_state").append(state);
}
