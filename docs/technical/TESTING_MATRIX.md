# CleanUx — Testing Matrix

## Routes et dashboards

    php artisan test tests/Feature/AdminRouteAccessTest.php
    php artisan test tests/Feature/ClientRouteAccessTest.php
    php artisan test tests/Feature/EmployeRouteAccessTest.php
    php artisan test tests/Feature/OptimizedDashboardExperienceSmokeTest.php
    php artisan test tests/Feature/LivewireViewIntegrityTest.php

## Booking

    php artisan test tests/Feature/RecurringBookingTest.php
    php artisan test tests/Feature/ZoneAwareReservationTest.php
    php artisan test tests/Feature/ZoneAwareStructuredReservationTest.php
    php artisan test tests/Feature/RecurringSeriesManagementTest.php

## Finance

    php artisan test tests/Feature/AdminFinanceCenterExperienceTest.php
    php artisan test tests/Feature/ClientFinanceDocumentsPortalTest.php
    php artisan test tests/Feature/FinanceCenterPilotableTest.php
    php artisan test tests/Feature/B2BMonthlyInvoiceTest.php

## B2B / Enterprise

    php artisan test tests/Feature/AdminB2BOperationsCenterTest.php
    php artisan test tests/Feature/EnterpriseApprovalWorkflowTest.php
    php artisan test tests/Feature/EnterpriseWorkOrderApprovalFlowTest.php
    php artisan test tests/Feature/GestionEntreprisesEnterpriseTest.php

## Missions employé

    php artisan test tests/Feature/EmployeMissionWorkspaceTest.php
    php artisan test tests/Feature/EmployeAdvancedCentersRouteIntegrationTest.php
    php artisan test tests/Feature/TeamLeadOperationsCenterTest.php
    php artisan test tests/Feature/TeamLeadWorkspaceAccessTest.php

## Support qualité

    php artisan test tests/Feature/SupportQualityAdvancedTest.php

## Gouvernance

    php artisan test tests/Feature/AdminSecurityHardeningTest.php
    php artisan test tests/Feature/ProductionHealthCheckCommandTest.php
    php artisan test tests/Feature/GoLiveReadinessReportCommandTest.php
    php artisan test tests/Feature/ConsolidationFinalCheckCommandTest.php

## Suite complète

    php artisan test

## Warnings acceptés

- API support is not enabled
- Registration support is enabled
- Deprecated ReflectionMethod::setAccessible avec Collision/PHP récent

## Erreurs non acceptées

- View not found
- Multiple root elements detected
- Route not defined
- Undefined method
- cURL external call pendant les tests
- Status 500 sur une route dashboard
