{crmScript ext=org.project60.sepa file=js/angular/angular.min.js}
{crmScript ext=org.project60.sepa file=js/angularControllerPackages.js}
{crmStyle ext=org.project60.sepa file=css/List.css}

<div ng-app="PackageMandateApp">
    <div ng-controller="PackageMandateCtrl" id="ListCtrl">
        <div class="searchFields">
            <div id='divSelectGroup' class="queryField">
                <label for="thisStatus" class="calloutRow">{ts}Status{/ts}</label>
                <select class="filterField" id="thisStatus" name="field_status">
                    <option value="">{ts}- select -{/ts}</option>
                    <option value="SUBMITTED">SUBMITTED</option>
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
                <th>{ts}Filename{/ts}</th>
                <th>{ts}Create date{/ts}</th>
                <th>{ts}Submission date{/ts}</th>
                <th><a ng-click="sort('status')">{ts}Status{/ts}</a></th>
                <th>{ts}Download{/ts}</th>
            </tr>
            </thead>
            <tbody id="resultTableBody">
            <tr id="resultRow" ng-repeat="item in list | filter:searchText | orderBy:predicate:reverse">
                <td id="id">[[item.id]]</td>
                <td id="filename">[[item.filename]]</td>
                <td id="create_date">[[item.create_date]]</td>
                <td id="submission_date">[[item.submission_date]]</td>
                <td id="status">[[item.status]]</td>
                <td id="download"><a href="{crmURL p="civicrm/sepa/dpackage" q="pid=[[item.id]]"}" download="[[item.filename]]" class="button">[[item.filename]]</a></td>
            </tr>
            </tbody>
        </table>
    </div>
</div>
