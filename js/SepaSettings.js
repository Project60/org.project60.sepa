/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2020                                     |
| Author: B. Endres (endres@systopia.de)                 |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

/**
 * These are batching parameters that are overwrites to the general settings
 *  and are not stored in the creditor itself
 */
const customBatchingParams = [
    ["cycledays_override", "custom_cycledays", null],
    ["batching_OOFF_horizon_override", "custom_OOFF_horizon", null],
    ["batching_OOFF_notice_override", "custom_OOFF_notice", null],
    ["batching_RCUR_horizon_override", "custom_RCUR_horizon", null],
    ["batching_RCUR_grace_override", "custom_RCUR_grace", null],
    ["batching_RCUR_notice_override", "custom_RCUR_notice", null],
    ["batching_FRST_notice_override", "custom_FRST_notice", null],
    ["custom_txmsg_override", "custom_txmsg", null]
];



// INITIALISATION
cj('#edit_creditor_id').val("none");
cj(function () {
    CRM.api3('Domain', 'getsingle', {
        'sequential': 1,
        'return': 'version'
    }).done(function (result) {
        if (result['is_error'] === 0) {
            var raw_version = result['version'].split('.', 3);
            var version = [];

            cj.each(raw_version, function (k, v) {
                version[k] = parseInt(v, 10);
            });


            cj('#addcreditor_creditor_id').autocomplete({
                source: function (request, response) {
                    var
                        option = cj('#addcreditor_creditor_id'),
                        params = {
                            'sequential': 1,
                            'sort_name': option.val()
                        };
                    CRM.api3('Contact', 'get', params).done(function (result) {
                        var ret = [];
                        if (result.values) {
                            cj.each(result.values, function (k, v) {
                                ret.push({value: v.contact_id, label: "[" + v.contact_id + "] " + v.display_name});
                            })
                        }
                        response(ret);
                    })
                },
                focus: function (event, ui) {
                    return false;
                },
                select: function (event, ui) {
                    cj('#addcreditor_creditor_id').val(ui.item.label);
                    cj('#add_creditor_id').val(ui.item.value);
                    return false;
                }
            });

            // // add a function to set default values for SEPA creditors
            cj('#addcreditor_type').change(setDefaultSEPAPaymentInstruments);
        }
    });
});

/**
 * Delete the given creditor
 * @param id creditor ID
 */
function deletecreditor(id) {

    CRM.confirm(function () {
            CRM.api3('SepaCreditor', 'delete', {'q': 'civicrm/ajax/rest', 'sequential': 1, 'id': id},
                {
                    success: function (data) {
                        CRM.api3('Setting', 'get', {'q': 'civicrm/ajax/rest', 'sequential': 1},
                            {
                                success: function (data) {
                                    if (data['is_error'] == 0) {
                                        cj.each(data["values"], function (key, value) {
                                            if (value.batching_default_creditor == id) {
                                                CRM.api3('Setting', 'create', {'batching_default_creditor': '0'}, {
                                                    success: function (data) {
                                                    }
                                                });
                                            }
                                        });

                                        CRM.alert(ts("Creditor deleted"), ts("Success"), "success");
                                        location.reload();
                                    }
                                }
                            }
                        );
                    }
                }
            );
            resetValues();
        },
        {
            message: ts("Are you sure you want to delete this creditor?")
        }
    );
}


// This function is needed due to the asynchronous call of success() in CRM.api3().
function createCallback(data, map, i, creditorId) {
    return function (data) {
        if (data['is_error'] == 0) {
            var result = "";

            if (data['result'] != "undefined") {
                result = cj.parseJSON(data['result']);
                customBatchingParams[i][2] = result;
            }

            if (result[creditorId] != undefined) {
                cj("#" + map[i][1]).val(result[creditorId]);
            } else {
                cj("#" + map[i][1]).val("");
            }
        }
    }
}

/**
 * Fetch creditor information
 * @param id the creditor ID
 * @param isCopy flag to indicate copying of a creditor
 */
function fetchCreditor(id, isCopy) {
    CRM.api3('SepaCreditor', 'getsingle', {'q': 'civicrm/ajax/rest', 'sequential': 1, 'id': id},
        {
            success: function (data) {
                if (data['is_error'] == 0) {
                    if (!isCopy) {
                        cj('#edit_creditor_id').val(data['id']);
                    } else {
                        cj('#edit_creditor_id').val("none");
                    }
                    cj('#add_creditor_id').val(data['creditor_id']);
                    cj('#addcreditor_label').val(data['label']);
                    cj('#addcreditor_name').val(data['name']);
                    cj('#addcreditor_address').val(data['address']);
                    cj('#addcreditor_country_id').val(data['country_id']);
                    cj('#addcreditor_id').val(data['identifier']);
                    cj('#addcreditor_iban').val(data['iban']);
                    cj('#addcreditor_bic').val(data['bic']);
                    cj('#addcreditor_pi_ooff').val(data['pi_ooff'] ? data['pi_ooff'].split(',') : []).change();
                    cj('#addcreditor_pi_rcur').val(data['pi_rcur'] ? data['pi_rcur'].split(',') : []).change();
                    cj("#addcreditor_pain_version").val(data['sepa_file_format_id']);
                    cj("#addcreditor_currency").val(data['currency']);
                    cj("#addcreditor_type").val(data['creditor_type']);
                    cj("#addcreditor_uses_bic").prop("checked", (data['uses_bic'] == "1"));
                    cj("#addcreditor_cuc").val(data['cuc']);
                    cj("#is_test_creditor").prop("checked", (data['category'] == "TEST"));
                    cj('#addcreditor').show(500);

                    CRM.api3('Contact', 'getsingle', {
                            'q': 'civicrm/ajax/rest',
                            'sequential': 1,
                            'id': data['creditor_id']
                        },
                        {
                            success: function (data2) {
                                if (data2['is_error'] == 0) {
                                    cj('#addcreditor_creditor_id').val("[" + data2['id'] + "] " + data2['display_name']);
                                }
                            }
                        });

                    for (var i = 0; i < customBatchingParams.length; i++) {
                        CRM.api3('Setting', 'getvalue', {
                            'q': 'civicrm/ajax/rest',
                            'sequential': 1,
                            'group': 'SEPA Direct Debit Preferences',
                            'name': customBatchingParams[i][0]
                        }, {success: createCallback(data, customBatchingParams, i, id)});
                    }
                }
            }
        }
    );
}

/**
 * Use an AJAX call to update the creditor with the currrent form values
 */
function updateCreditor() {
    let inputCreditorInfo = cj("#addcreditor #creditorinfo :input").serializeArray();
    let inputCustomBatching = cj("#addcreditor #custombatching :input").serializeArray();
    inputCustomBatching.push({'name': "custom_txmsg", 'value': cj('#custom_txmsg').val()});
    let creditorId = cj('#edit_creditor_id').val();

    let map = {
        "edit_creditor_id":         "id",
        "addcreditor_label":        "label",
        "addcreditor_name":         "name",
        "addcreditor_address":      "address",
        "addcreditor_country_id":   "country_id",
        "addcreditor_currency":     "currency",
        "addcreditor_id":           "identifier",
        "addcreditor_iban":         "iban",
        "addcreditor_bic":          "bic",
        "addcreditor_pain_version": "sepa_file_format_id",
        "addcreditor_type":         "creditor_type",
        "addcreditor_creditor_id":  "creditor_id",
        "addcreditor_uses_bic":     "uses_bic",
        "addcreditor_cuc":          "cuc",
        "custom_txmsg":             "custom_txmsg"};

    // update creditor information
    let updatedCreditorInfo = new Array();
    for (let i = 0; i < inputCreditorInfo.length; i++) {
        let name = map[(inputCreditorInfo[i]["name"])] || inputCreditorInfo[i]["name"];
        let value = inputCreditorInfo[i]["value"];
        if (name == "creditor_id") {
            value = cj('#add_creditor_id').val();
        }
        if (value != "") {
            updatedCreditorInfo[name] = value;
        }
    }

    // add payment instruments
    updatedCreditorInfo['pi_ooff'] = cj("#addcreditor_pi_ooff").val() ? cj("#addcreditor_pi_ooff").val().join(',') : '';
    updatedCreditorInfo['pi_rcur'] = cj("#addcreditor_pi_rcur").val() ? cj("#addcreditor_pi_rcur").val().join(',') : '';

    if (cj('#is_test_creditor').is(':checked')) {
        updatedCreditorInfo['category'] = "TEST";
    } else {
        updatedCreditorInfo['category'] = "";
    }

    if (cj('#addcreditor_uses_bic').is(':checked')) {
        updatedCreditorInfo['uses_bic'] = "1";
    } else {
        updatedCreditorInfo['uses_bic'] = "0";
    }

    let stdObj = {'q': 'civicrm/ajax/rest', 'sequential': 1, 'mandate_active': 1};
    if (creditorId != "none") {
        stdObj.id = creditorId;
    }

    if (updatedCreditorInfo['creditor_id'] === undefined) {
        CRM.alert(ts("You must provide a valid contact to save this creditor"), ts("Error"), "error");
        return;
    }

    if (!(updatedCreditorInfo['pi_ooff'].length + updatedCreditorInfo['pi_rcur'].length)) {
        CRM.alert(ts("You need to set at least one payment instrument"), ts("Error"), "error");
        return;
    }

    let reIBAN = /^[A-Z0-9]+$/;
    if (!reIBAN.test(updatedCreditorInfo['iban'])) {
        CRM.alert(ts("IBAN is not correct"), ts("Error"), "error");
        return;
    }

    cj(".save").addClass("disabled");
    cj(".save").attr('onclick', '').unbind('click');

    let updObj = cj.extend(stdObj, updatedCreditorInfo);
    CRM.api3('SepaCreditor', 'create', updObj,
        {
            success: function (data) {
                if (data['is_error'] == 0) {
                    // check whether we updated an existing creditor
                    // or created a new one
                    let creditorId = cj('#edit_creditor_id').val();
                    if (creditorId == "none") {
                        creditorId = data['values'][0]['id'];
                    }

                    // update creditor batching settings
                    for (let i = 0; i < customBatchingParams.length; i++) {
                        let name = customBatchingParams[i][0];
                        let value = inputCustomBatching[i].value;
                        let param = {};

                        // modify the object from the database if it exists
                        if (customBatchingParams[i][2] !== null) {
                            param[name] = customBatchingParams[i][2];
                        } else {
                            param[name] = {};
                        }

                        if (value != "") {
                            param[name][creditorId] = value;
                        } else {
                            delete param[name][creditorId];
                        }

                        param[name] = JSON.stringify(param[name]);
                        var once = true;
                        CRM.api3('Setting', 'create', param, {
                            success: function (data) {
                                if (once) {
                                    once = !once;
                                    CRM.alert(ts("Creditor updated"), ts("Success"), "success");
                                    resetValues();
                                    location.reload();
                                }
                            }
                        });
                    }
                }
            }
        }
    );
}

/**
 * If the chosen creditor type is SEPA and there
 *   are no payment instruments chose, this sets them to the standard
 */
function setDefaultSEPAPaymentInstruments() {
    if (cj('#addcreditor_type').val() == 'SEPA') {
        if (cj("#addcreditor_pi_ooff").val() || cj("#addcreditor_pi_rcur").val()) {
            // there is some values set -> leave them alone
        } else {
            // set some defaults
            cj("#addcreditor_pi_ooff").val(CRM.vars.p60sdd.ooff_sepa_default).change();
            cj("#addcreditor_pi_rcur").val(CRM.vars.p60sdd.rcur_sepa_default).change();
        }
    }
}

/**
 * Reset creditor form
 */
function resetValues() {
    cj('#custombatching :input').val("");
    cj('#creditorinfo :input').val("");
    cj('#edit_creditor_id').val("none");
    cj('#add_creditor_id').val("");
    cj('#addcreditor_type').val("SEPA");
    cj('#add_uses_bic').prop('checked', false);
    cj('#addcreditor_pi_ooff').val('').change();
    cj('#addcreditor_pi_rcur').val('').change();
    setDefaultSEPAPaymentInstruments();
}