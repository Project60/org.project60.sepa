{crmScript ext=org.project60.sepa file=js/angular/angular.min.js}
{crmScript ext=org.project60.sepa file=js/angularControllerMandates.js}
{crmScript ext=org.project60.sepa file=js/list.js}
{crmStyle ext=org.project60.sepa file=css/List.css}

<div ng-app="ListMandateApp">
    <div ng-controller="ListMandateCtrl" id="ListCtrl">
        <div class="searchFields">
            <div id='divSelectGroup' class="queryField">
                <label for="thisStatus" class="calloutRow">{ts}Status{/ts}</label>
                <select class="filterField" id="thisStatus" name="field_status">
                    <option value="">{ts}- select -{/ts}</option>
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
            <button id='bGetList' class='button' type='button' ng-click='getList()'>
                {ts}Find{/ts}
            </button>
        </div>
        <div id="loadingHeader"></div>
        <table id="resultTable" class="selector row-highlight">
            <thead>
            <tr>
                <th>{ts}ID{/ts}</th>
                <th>{ts}Contact{/ts}</th>
                <th>{ts}Reference{/ts}</th>
                <th>{ts}IBAN / BIC{/ts}</th>
                <th><a ng-click="sort('status')">{ts}Status{/ts}</a></th>
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
            </tr>
            </tbody>
        </table>
    </div>
</div>