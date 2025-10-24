<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProcessFlow;
use App\Models\WorkflowStep;
use App\Contracts\WorkflowEngineInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WorkflowTester
{
    protected WorkflowEngineInterface $workflowEngine;

    public function __construct(WorkflowEngineInterface $workflowEngine)
    {
        $this->workflowEngine = $workflowEngine;
    }

    /**
     * Test a complete workflow with sample data.
     */
    public function testWorkflow(ProcessFlow $processFlow, array $sampleOrderData = []): array
    {
        $results = [
            'success' => true,
            'steps_tested' => 0,
            'steps_passed' => 0,
            'steps_failed' => 0,
            'errors' => [],
            'step_results' => [],
        ];

        try {
            // Create a test order
            $testOrder = $this->createTestOrder($sampleOrderData);
            
            // Test process flow conditions
            $conditionsResult = $this->testProcessFlowConditions($processFlow, $testOrder);
            $results['process_flow_conditions'] = $conditionsResult;

            if (!$conditionsResult['passed']) {
                $results['success'] = false;
                $results['errors'][] = 'Process flow conditions failed';
                return $results;
            }

            // Test each workflow step
            foreach ($processFlow->workflowSteps()->orderBy('step_order')->get() as $step) {
                $stepResult = $this->testWorkflowStep($step, $testOrder);
                $results['step_results'][] = $stepResult;
                $results['steps_tested']++;

                if ($stepResult['passed']) {
                    $results['steps_passed']++;
                } else {
                    $results['steps_failed']++;
                    $results['errors'] = array_merge($results['errors'], $stepResult['errors']);
                }
            }

            $results['success'] = $results['steps_failed'] === 0;

        } catch (\Exception $e) {
            $results['success'] = false;
            $results['errors'][] = "Workflow test failed: {$e->getMessage()}";
            Log::error('Workflow test error', [
                'process_flow_id' => $processFlow->id,
                'error' => $e->getMessage()
            ]);
        }

        return $results;
    }

    /**
     * Test a single workflow step.
     */
    public function testWorkflowStep(WorkflowStep $step, Order $testOrder = null): array
    {
        $result = [
            'step_id' => $step->id,
            'step_name' => $step->name,
            'step_type' => $step->step_type,
            'passed' => true,
            'errors' => [],
            'conditions_result' => null,
            'execution_result' => null,
        ];

        try {
            // Use provided test order or create a default one
            if (!$testOrder) {
                $testOrder = $this->createTestOrder();
            }

            // Test step conditions
            if (!empty($step->conditions)) {
                $conditionsResult = $this->workflowEngine->evaluateConditions($testOrder, $step->conditions);
                $result['conditions_result'] = $conditionsResult;
                
                if (!$conditionsResult) {
                    $result['errors'][] = 'Step conditions evaluation failed';
                }
            }

            // Test step configuration validation
            $configValidation = $this->validateStepConfiguration($step);
            if (!$configValidation['valid']) {
                $result['passed'] = false;
                $result['errors'] = array_merge($result['errors'], $configValidation['errors']);
            }

            // Test step execution (dry run)
            $executionResult = $this->dryRunStepExecution($step, $testOrder);
            $result['execution_result'] = $executionResult;
            
            if (!$executionResult['success']) {
                $result['passed'] = false;
                $result['errors'] = array_merge($result['errors'], $executionResult['errors']);
            }

        } catch (\Exception $e) {
            $result['passed'] = false;
            $result['errors'][] = "Step test failed: {$e->getMessage()}";
        }

        return $result;
    }    /*
*
     * Test process flow conditions.
     */
    private function testProcessFlowConditions(ProcessFlow $processFlow, Order $testOrder): array
    {
        $result = [
            'passed' => true,
            'conditions' => $processFlow->conditions ?? [],
            'errors' => [],
        ];

        try {
            if (!empty($processFlow->conditions)) {
                $result['passed'] = $this->workflowEngine->evaluateConditions($testOrder, $processFlow->conditions);
                
                if (!$result['passed']) {
                    $result['errors'][] = 'Process flow conditions not met with test data';
                }
            }
        } catch (\Exception $e) {
            $result['passed'] = false;
            $result['errors'][] = "Condition evaluation error: {$e->getMessage()}";
        }

        return $result;
    }

    /**
     * Create a test order with sample data.
     */
    private function createTestOrder(array $sampleData = []): Order
    {
        $defaultData = [
            'platform_order_id' => 'TEST-' . uniqid(),
            'platform_type' => 'shopee',
            'customer_name' => 'Test Customer',
            'customer_email' => 'test@example.com',
            'customer_phone' => '+1234567890',
            'total_amount' => 100.00,
            'currency' => 'USD',
            'status' => 'pending',
            'workflow_status' => 'new',
            'order_date' => now(),
            'sync_status' => 'synced',
            'raw_data' => [
                'payment_method' => 'credit_card',
                'shipping_method' => 'standard',
                'priority' => 'normal',
            ],
        ];

        $orderData = array_merge($defaultData, $sampleData);

        // Create a temporary order for testing (not saved to database)
        return new Order($orderData);
    }

    /**
     * Validate step configuration.
     */
    private function validateStepConfiguration(WorkflowStep $step): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
        ];

        $config = $step->configuration ?? [];

        // Validate based on step type
        switch ($step->step_type) {
            case 'automatic':
                if (empty($config)) {
                    $result['errors'][] = 'Automatic steps require configuration';
                    $result['valid'] = false;
                }
                break;

            case 'notification':
                if (!isset($config['type']) || !isset($config['message'])) {
                    $result['errors'][] = 'Notification steps require type and message configuration';
                    $result['valid'] = false;
                }
                break;

            case 'approval':
                if (!$step->assigned_role && !isset($config['approver_role'])) {
                    $result['errors'][] = 'Approval steps require assigned role or approver role configuration';
                    $result['valid'] = false;
                }
                break;
        }

        return $result;
    }

    /**
     * Perform a dry run of step execution.
     */
    private function dryRunStepExecution(WorkflowStep $step, Order $testOrder): array
    {
        $result = [
            'success' => true,
            'errors' => [],
            'would_execute' => false,
        ];

        try {
            // Check if conditions would allow execution
            if (!empty($step->conditions)) {
                $conditionsMet = $this->workflowEngine->evaluateConditions($testOrder, $step->conditions);
                $result['would_execute'] = $conditionsMet;
                
                if (!$conditionsMet) {
                    $result['errors'][] = 'Step conditions not met, would not execute';
                }
            } else {
                $result['would_execute'] = true;
            }

            // Validate step type specific requirements
            switch ($step->step_type) {
                case 'manual':
                case 'approval':
                case 'billing':
                case 'packing':
                case 'return':
                    // These require task assignment
                    if (!$step->assigned_role) {
                        $result['errors'][] = 'Manual steps should have an assigned role for proper task assignment';
                    }
                    break;

                case 'automatic':
                    // Validate automatic step configuration
                    $config = $step->configuration ?? [];
                    if (empty($config)) {
                        $result['errors'][] = 'Automatic steps require configuration to define actions';
                    }
                    break;
            }

        } catch (\Exception $e) {
            $result['success'] = false;
            $result['errors'][] = "Dry run failed: {$e->getMessage()}";
        }

        return $result;
    }

    /**
     * Generate test report.
     */
    public function generateTestReport(array $testResults): string
    {
        $report = "Workflow Test Report\n";
        $report .= "===================\n\n";
        
        $report .= "Overall Result: " . ($testResults['success'] ? 'PASSED' : 'FAILED') . "\n";
        $report .= "Steps Tested: {$testResults['steps_tested']}\n";
        $report .= "Steps Passed: {$testResults['steps_passed']}\n";
        $report .= "Steps Failed: {$testResults['steps_failed']}\n\n";

        if (!empty($testResults['errors'])) {
            $report .= "Errors:\n";
            foreach ($testResults['errors'] as $error) {
                $report .= "- {$error}\n";
            }
            $report .= "\n";
        }

        if (!empty($testResults['step_results'])) {
            $report .= "Step Details:\n";
            foreach ($testResults['step_results'] as $stepResult) {
                $status = $stepResult['passed'] ? 'PASS' : 'FAIL';
                $report .= "- {$stepResult['step_name']} ({$stepResult['step_type']}): {$status}\n";
                
                if (!empty($stepResult['errors'])) {
                    foreach ($stepResult['errors'] as $error) {
                        $report .= "  * {$error}\n";
                    }
                }
            }
        }

        return $report;
    }
}