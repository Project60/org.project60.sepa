{*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2016 Project60                      |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+-------------------------------------------------------*}

{crmScript ext=org.project60.sepa file=js/angular/angular.min.js}
{crmScript ext=org.project60.sepa file=js/ListMandate.js}
{crmScript ext=org.project60.sepa file=js/angularController.js}
{crmStyle ext=org.project60.sepa file=css/ListMandate.css}

<div ng-app="ListMandateApp">
    <div ng-controller="ListMandateCtrl" id="ListMandateCtrl">
        <div class="searchFields">
            <div id='divSelectGroup' class="queryField">
                <label for="thisStatus" class="calloutRow">{ts domain="org.project60.sepa"}Status{/ts}</label>
                <select class="filterField" id="thisStatus" name="field_status">
                    <option value="">{ts domain="org.project60.sepa"}- select -{/ts}</option>
                    <option value="INIT">INIT</option>
                    <option value="OOFF">OOFF</option>
                    <option value="FRST">FRST</option>
                    <option value="RCUR">RCUR</option>
                    <option value="SENT">SENT</option>
                    <option value="INVALID">INVALID</option>
                    <option value="PARTIAL">PARTIAL</option>
                    <option value="ONHOLD">ONHOLD</option>
                    <option value="COMPLETE">COMPLETE</option>
                </select>
            </div>
            <button id='bGetMandates' class='button' type='button' ng-click='getMandates()'>
                {ts domain="org.project60.sepa"}Find{/ts}
            </button>
        </div>
        <div id="loadingHeader"></div>
        <table id="resultTable" class="selector row-highlight">
            <thead>
            <tr>
                <th><a ng-click="sort('id')">{ts domain="org.project60.sepa"}ID{/ts}</a></th>
                <th><a ng-click="sort('contact.display_name')">{ts domain="org.project60.sepa"}Contact{/ts}</a></th>
                <th><a ng-click="sort('reference')">{ts domain="org.project60.sepa"}Reference{/ts}</></th>
                <th><a ng-click="sort('iban')">{ts domain="org.project60.sepa"}IBAN / BIC{/ts}</></th>
                <th><a ng-click="sort('status')">{ts domain="org.project60.sepa"}Status{/ts}</a></th>
                <th>&nbsp;</th>
            </tr>
            </thead>
            <tbody id="resultTableBody">
            <tr id="resultRow" ng-repeat="mandate in mandates | filter:searchText | orderBy:predicate:reverse">
                <td id="id">[[mandate.id]]</td>
                <td id="contact_id">
                    <a href="{crmURL p="civicrm/contact/view" q="reset=1&cid=[[mandate.contact_id]]"}">
                        [[mandate.contact.display_name]]
                    </a>
                </td>
                <td id="reference">[[mandate.reference]]</td>
                <td id="iban">[[mandate.iban]]<br>[[mandate.bic]]</td>
                <td id="status">[[mandate.status]]</td>
                <td id="action"><a href="{crmURL p="civicrm/sepa/xmandate" q="mid=[[mandate.id]]"}" class="action-item crm-hover-button">Edit</a></td>
            </tr>
            </tbody>
        </table>
    </div>
</div>