/* global shippingZoneMethodsLocalizeScript, ajaxurl */
(function($, data, wp, ajaxurl) {
    $(window).on("load", function() {
        $("select#charges_option").change();
        $("select#courier_services").change();
        $("input#free_shipping").change();
        if (data.type == "edit") {
            if (data.service_id != "all" || data.service_id != "cheapest") {
                $("tr#courier_display_name_panel").css("display", "table-row");
            }
        }
    });
    $(function() {
        $("select#charges_option").change(function() {
            var charges_option = $("select#charges_option option:selected").val();
            if (charges_option == "1") {
                $("tr#addon_4").css("display", "none");
                $("tr#addon_1").css("display", "table-row");
            } else if (charges_option == "4") {
                $("tr#addon_4").css("display", "table-row");
                $("tr#addon_1").css("display", "none");
            } else {
                $("tr#addon_4").css("display", "none");
                $("tr#addon_1").css("display", "none");
            }
        });
        $("select#courier_services").change(function(e) {
            if ($(e.target).find(":selected").attr("data-service_id") != "all" && $(e.target).find(":selected").attr("data-service_id") != "cheapest") {
                $("tr#courier_display_name_panel").css("display", "table-row");
                $("#courier_display_name").val($("#courier_services option:selected").text());
                $("#courier_display_name").val($("#courier_services option:selected").attr("data-service_name") + " [" + $("#courier_services option:selected").attr("data-courier_info") + "]");
                $("#courier_service_img").attr("src", $("#courier_services option:selected").attr("data-courier_logo"));
                //check if got dropoff point
                if ($("#courier_services option:selected").attr("data-dropoff") == "yes") {
                    var list = '<option value="optional">[Optional] Drop Off Point</option>';
                    list += $("#" + $("#courier_services option:selected").attr("data-courier_id")).html();
                    $("select#dropoff_point").html(list);
                    $("tr#courier_dropoff_panel").css("display", "table-row");
                } else {
                    $("tr#courier_dropoff_panel").css("display", "none");
                }
            } else {
                $("#courier_display_name").val($(e.target).find(":selected").attr("data-service_name"));
                $("#courier_service_img").attr("src", "");
                $("tr#courier_display_name_panel").css("display", "none");
                //check if got dropoff point
                if ($("#courier_services option:selected").attr("data-dropoff") == "yes") {
                    var list = '<option value="optional">[Optional] Drop Off Point</option>';
                    list += $("#" + $("#courier_services option:selected").attr("data-courier_id")).html();
                    $("select#dropoff_point").html(list);
                    $("tr#courier_dropoff_panel").css("display", "table-row");
                } else {
                    $("tr#courier_dropoff_panel").css("display", "none");
                }
            }
        });

        $("input#free_shipping").change(function() {
            if (this.checked) {
                $("tr.free_shipping_tab").css("display", "table-row");
            } else {
                $("tr.free_shipping_tab").css("display", "none");
            }
        });

        $("select#free_shipping_by").change(function() {
            var free_shipping_by = $("select#free_shipping_by option:selected").val();
            if (free_shipping_by == "1") {
                $("span#free_shipping_text").text("Minimum Order Amount");
            } else {
                $("span#free_shipping_text").text("Minimum Order Quantity");
            }
        });

        $(".setup_courier button#submit").click(function(e) {
            $(window).off("beforeunload");
            window.onbeforeunload = null;
            var result = doSetupCourierCheck(data);
            if (result) {
                $.post(
                    ajaxurl + (ajaxurl.indexOf("?") > 0 ? "&" : "?") + "action=ep_courier_setting_save_changes", {
                        ep_courier_setup_nonce: data.ep_courier_setup_nonce,
                        data: result,
                        zone_id: data.zone_id,
                        method: "insert",
                    },
                    function(response, textStatus) {
                        if ("success" === textStatus && response.success) {
                            data.zone_id = response.data.zone_id;
                            // perform save then go to another page
                            $(window).off("beforeunload");
                            window.onbeforeunload = null;
                            window.location.href = "admin.php?page=wc-settings&tab=shipping&section=easyparcel_shipping&zone_id=" + response.data.zone_id;
                            $(window).off("beforeunload");
                        } else if ("success" === textStatus && !response.success) {
                            if (response.data == "courier_name_exist") {
                                alert("Courier Display Name exist.\nKindly insert other name.");
                            }
                        }
                    }
                );
            }
            //action
            $(window).off("beforeunload");
            e.preventDefault();
        });

        $(".setup_courier button#back").click(function(e) {
            $(window).off("beforeunload");
            window.onbeforeunload = null;
            window.history.back();
            //action
            $(window).off("beforeunload");
            e.preventDefault();
        });

        $(".edit_courier button#submit").click(function(e) {
            $(window).off("beforeunload");
            window.onbeforeunload = null;
            var result = doEditCourierCheck(data);
            if (result) {
                $.post(
                    ajaxurl + (ajaxurl.indexOf("?") > 0 ? "&" : "?") + "action=ep_courier_setting_save_changes", {
                        ep_courier_setup_nonce: data.ep_courier_setup_nonce,
                        data: result,
                        courier_id: data.courier_id,
                        method: "update",
                    },
                    function(response, textStatus) {
                        if ("success" === textStatus && response.success) {
                            data.zone_id = response.data.zone_id;
                            // perform save then go to another page
                            $(window).off("beforeunload");
                            window.onbeforeunload = null;
                            // window.history.back();
                            window.location.href = "admin.php?page=wc-settings&tab=shipping&section=easyparcel_shipping&zone_id=" + response.data.zone_id;
                            $(window).off("beforeunload");
                        } else if ("success" === textStatus && !response.success) {
                            if (response.data == "courier_name_exist") {
                                alert("Courier Display Name exist.\nKindly insert other name.");
                            }
                        }
                    }
                );
            }
            //action
            $(window).off("beforeunload");
            e.preventDefault();
        });

        $(".edit_courier button#back").click(function(e) {
            $(window).off("beforeunload");
            window.onbeforeunload = null;
            window.history.back();
            //action
            $(window).off("beforeunload");
            e.preventDefault();
        });

        function doSetupCourierCheck(data) {
            var courier_service = $("#courier_services option:selected").attr("data-service_id");
            var service_name = $("#courier_services option:selected").attr("data-service_name") + " [" + $("#courier_services option:selected").attr("data-courier_info") + "]";
            var courier_id = $("#courier_services option:selected").attr("data-courier_id");
            var courier_name = $("#courier_services option:selected").attr("data-courier_name");
            var courier_logo = $("#courier_services option:selected").attr("data-courier_logo");
            var courier_info = $("#courier_services option:selected").attr("data-courier_info");
            var dropoff_option = $("#courier_services option:selected").attr("data-dropoff");
            var courier_display_name = $("input#courier_display_name").val();
            var courier_sample_cost = $("#courier_services option:selected").attr("data-sample_cost");
            var charges_option = $("select#charges_option option:selected").val();
            var charges_value = 0;
            var dropoff_point = "";
            var err_msg = "";
            if (charges_option == 1) {
                charges_value = $("input#addon_1_charges_value_1").val();

                //validate
                var price_regex = /^\d+(?:\.\d{0,2})$/;
                if (charges_value == null || charges_value == undefined || charges_value == "") {
                    err_msg += "Your add on option value cannot be blank.\n";
                } else {
                    charges_value = parseFloat(charges_value).toFixed(2);
                    if (!price_regex.test(charges_value)) {
                        err_msg += "Your add on option value must be number.\n";
                    }
                }
            } else if (charges_option == 4) {
                var option = $("select#addon_4_charges_value_1 option:selected").val();
                var value = $("input#addon_4_charges_value_2").val();
                //validate
                var price_regex = /^\d+(?:\.\d{0,2})$/;
                if (value == null || value == undefined || value == "") {
                    err_msg += "Your add on option value cannot be blank.\n";
                } else {
                    value = parseFloat(value).toFixed(2);
                    // if (!price_regex.test(value)){
                    if (isNaN(value)) {
                        err_msg += "Your add on option value must be number.\n";
                    }
                    charges_value = option + ":" + value;
                }
            }
            var free_shipping_option = 2; // make default no freeshipping
            var free_shipping_by = 0; // make default
            var free_shipping_value = 0; // make default
            if ($("input#free_shipping").is(":checked")) {
                free_shipping_option = 1;
                free_shipping_by = $("select#free_shipping_by option:selected").val();
                free_shipping_value = $("input#free_shipping_value").val();
                //validate
                if (free_shipping_by == "1") {
                    var price_regex = /^\d+(?:\.\d{0,2})$/;
                    if (free_shipping_value == null || free_shipping_value == undefined || free_shipping_value == "") {
                        err_msg += "Your minimum order amount cannot be blank.\n";
                    } else {
                        free_shipping_value = parseFloat(free_shipping_value).toFixed(2);
                        if (!price_regex.test(free_shipping_value)) {
                            err_msg += "Your minimum order amount must be number.\n";
                        }
                    }
                } else if (free_shipping_by == "2") {
                    if (free_shipping_value == null || free_shipping_value == undefined || free_shipping_value == "") {
                        err_msg += "Your minimum order quantity cannot be blank.\n";
                    } else {
                        free_shipping_value = parseFloat(free_shipping_value);
                        if (isNaN(free_shipping_value)) {
                            err_msg += "Your minimum order quantity must be integer.\n";
                        } else if (free_shipping_value <= 0) {
                            err_msg += "Your minimum order quantity must be negative value.\n";
                        }
                    }
                }
            }

            //dropoff checking
            if (dropoff_option == "yes") {
                dropoff_point = $("select#dropoff_point option:selected").val();
            }
            //perform validation
            if (err_msg != "") {
                alert(err_msg);
                return false;
            } else {
                var data = {
                    zone_id: data.zone_id,
                    courier_service: courier_service,
                    service_name: service_name,
                    courier_id: courier_id,
                    courier_name: courier_name,
                    courier_logo: courier_logo,
                    courier_display_name: courier_display_name,
                    courier_info: courier_info,
                    courier_dropoff_point: dropoff_point,
                    sample_cost: courier_sample_cost,
                    charges_option: charges_option,
                    charges_value: charges_value,
                    free_shipping: free_shipping_option,
                    free_shipping_by: free_shipping_by,
                    free_shipping_value: free_shipping_value,
                };
                return data;
            }
        }

        function doEditCourierCheck(data) {
            var courier_display_name = $("input#courier_display_name").val();
            var charges_option = $("select#charges_option option:selected").val();
            var dropoff_option = $("tr#courier_dropoff_panel").length;
            var dropoff_point = "";
            var charges_value = 0;
            var err_msg = "";
            if (charges_option == 1) {
                charges_value = $("input#addon_1_charges_value_1").val();

                //validate
                var price_regex = /^\d+(?:\.\d{0,2})$/;
                if (charges_value == null || charges_value == undefined || charges_value == "") {
                    err_msg += "Your add on option value cannot be blank.\n";
                } else {
                    charges_value = parseFloat(charges_value).toFixed(2);
                    if (!price_regex.test(charges_value)) {
                        err_msg += "Your add on option value must be number.\n";
                    }
                }
            } else if (charges_option == 4) {
                var option = $("select#addon_4_charges_value_1 option:selected").val();
                var value = $("input#addon_4_charges_value_2").val();
                //validate
                var price_regex = /^\d+(?:\.\d{0,2})$/;
                if (value == null || value == undefined || value == "") {
                    err_msg += "Your add on option value cannot be blank.\n";
                } else {
                    value = parseFloat(value).toFixed(2);
                    // if (!price_regex.test(value)){
                    if (isNaN(value)) {
                        err_msg += "Your add on option value must be number.\n";
                    }
                    charges_value = option + ":" + value;
                }
            }
            var free_shipping_option = 2; // make default no freeshipping
            var free_shipping_by = 0; // make default
            var free_shipping_value = 0; // make default
            if ($("input#free_shipping").is(":checked")) {
                free_shipping_option = 1;
                free_shipping_by = $("select#free_shipping_by option:selected").val();
                free_shipping_value = $("input#free_shipping_value").val();
                //validate
                if (free_shipping_by == "1") {
                    var price_regex = /^\d+(?:\.\d{0,2})$/;
                    if (free_shipping_value == null || free_shipping_value == undefined || free_shipping_value == "") {
                        err_msg += "Your minimum order amount cannot be blank.\n";
                    } else {
                        free_shipping_value = parseFloat(free_shipping_value).toFixed(2);
                        if (!price_regex.test(free_shipping_value)) {
                            err_msg += "Your minimum order amount must be number.\n";
                        }
                    }
                } else if (free_shipping_by == "2") {
                    if (free_shipping_value == null || free_shipping_value == undefined || free_shipping_value == "") {
                        err_msg += "Your minimum order quantity cannot be blank.\n";
                    } else {
                        free_shipping_value = parseFloat(free_shipping_value);
                        if (isNaN(free_shipping_value)) {
                            err_msg += "Your minimum order quantity must be integer.\n";
                        } else if (free_shipping_value <= 0) {
                            err_msg += "Your minimum order quantity must be negative value.\n";
                        }
                    }
                }
            }

            //dropoff checking
            if (dropoff_option > 0) {
                dropoff_point = $("select#dropoff_point option:selected").val();
            } else {
                dropoff_point = "";
            }

            //perform validation
            if (err_msg != "") {
                alert(err_msg);
                return false;
            } else {
                var data = {
                    courier_display_name: courier_display_name,
                    charges_option: charges_option,
                    courier_dropoff_point: dropoff_point,
                    charges_value: charges_value,
                    free_shipping: free_shipping_option,
                    free_shipping_by: free_shipping_by,
                    free_shipping_value: free_shipping_value,
                };
                return data;
            }
        }
    });
})(jQuery, shippingZoneMethodsLocalizeScript, wp, ajaxurl);