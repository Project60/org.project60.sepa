var myApp = angular.module('ListMandateApp', [])
    .config(['$interpolateProvider', function ($interpolateProvider) {
        $interpolateProvider.startSymbol('[[');
        $interpolateProvider.endSymbol(']]');
    }]);
  
myApp.controller('ListMandateCtrl', function ($scope) {
    $scope.mandates = [];
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
    
    $scope.getMandates = function() {
        var mandates = [];
        var status = cj('#thisStatus').val() || null;
        var loading = cj('#loadingHeader');
        loading.show();
        CRM.api3('SepaMandate', 'getlist', {
            "sequential": 1,
            "status": status
        }).done(function (data) {
            angular.forEach(data.values, function (key, value) {
                var mandate = {};
                mandate['id'] = key.id;
                mandate['contact_id'] = key.contact_id;
                mandate['contact'] = key.contact;
                mandate['reference'] = key.reference;
                mandate['iban'] = key.iban;
                mandate['bic'] = key.bic;
                mandate['status'] = key.status;
                mandates.push(mandate);
            });
            $scope.mandates = mandates;
            $scope.$apply();
        }).always(function(data){
            loading.hide();
        });
    };
});
