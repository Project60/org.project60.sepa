var myApp = angular.module('PackageMandateApp', [])
    .config(['$interpolateProvider', function ($interpolateProvider) {
        $interpolateProvider.startSymbol('[[');
        $interpolateProvider.endSymbol(']]');
    }]);
  
myApp.controller('PackageMandateCtrl', function ($scope) {
    $scope.list = [];
    $scope.predicate = 'status';
    $scope.reverse = false;
    
    $scope.sort = function(predicate) {
        if ($scope.predicate == predicate) {
            $scope.reverse = !$scope.reverse;
        } else {
            $scope.reverse = false;
        }
        $scope.predicate = predicate;
    }
    
    $scope.getList = function() {
        var list = [];
        var status = cj('#thisStatus').val() || null;
        var loading = cj('#loadingHeader');
        loading.show();
        CRM.api3('SepaMandateFile', 'getlist', {
            "sequential": 1
            ,
            "status": status
        }).done(function (data) {
            angular.forEach(data.values, function (key, value) {
                var item = {};
                item['id'] = key.id;
                item['contact_id'] = key.contact_id;
                item['creditor_id'] = key.creditor_id;
                item['filename'] = key.filename;
                item['create_date'] = key.create_date;
                item['submission_date'] = key.submission_date;
                item['status'] = key.status;
                list.push(item);
            });
            $scope.list = list;
            $scope.$apply();
        }).always(function(data){
            loading.hide();
        });
    };
});
