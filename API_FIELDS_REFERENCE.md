# API Request Body Fields Reference — Complete Extraction from Controllers & Models

> Auto-generated from actual controller code + model `$fillable` arrays.

---

## 1. PayComponentApiController

### `storeOrUpdate` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corpId` | `required\|string` |
| `puid` | `required\|string` |
| `componentName` | `required\|string` |

**Additional fields from `$request->all()` → Model $fillable:**
| Field | Type |
|---|---|
| `componentType` | string |
| `payType` | string |
| `paymentInterval` | string |
| `isPartOfCtcYn` | integer |
| `isPartOfGrossYn` | integer |
| `isIncludedInSalaryYn` | integer |
| `paymentNature` | string |
| `rqstVariableTypeEmpYn` | integer |
| `rqstVariableTypeManagerYn` | integer |
| `rqstVariableTypeHrYn` | integer |
| `isVariableAttachmentRequiredYn` | integer |
| `isProratedByPaidDaysYn` | integer |
| `arrearApplicableYn` | integer |
| `processInJoiningMonthYn` | integer |
| `processInSettlementMonthYn` | integer |
| `pfYn` | integer |
| `ptYn` | integer |
| `employeeStateInsuranceYn` | integer |
| `isIncludedForEsiCheck` | integer |
| `bonusYn` | integer |
| `isIncludedForBonusCheck` | integer |
| `labourWelfareFundYn` | integer |
| `gratuityYn` | integer |
| `leaveEncashmentYn` | integer |
| `roundOffConfiguration` | string |
| `isShownOnSalarySlip` | integer |
| `isShownOnSalaryRegister` | integer |
| `isShownRateOnSalarySlip` | integer |
| `salaryRegisterSortOrder` | integer |
| `taxConfigurationType` | string |
| `nonTaxableLimit` | string |
| `taxComputationMethodType` | string |
| `mapIncomeToSectionType` | string |
| `isIncludeInFBPBasket` | integer |
| `componentDescription` | string |

---

## 2. PaygroupConfigurationApiController

### `storeOrUpdate` (POST)
**No explicit validation** — uses `$request->all()`. Fields from Model $fillable:
| Field | Type |
|---|---|
| `corpId` | string (required for logic) |
| `puid` | string (used as key) |
| `GroupName` | string (required for logic) |
| `Description` | string |
| `Payslip` | string |
| `Reimbursement` | string |
| `TaxSlip` | string |
| `AppointmentLetter` | string |
| `SalaryRevisionLetter` | string |
| `ContractLetter` | string |
| `IncludedComponents` | string (comma-separated list) |
| `IsFBPEnabled` | integer |
| `PfWageComponentsUnderThreshold` | string |
| `CtcYearlyYn` | integer |
| `MonthlyBasicYn` | integer |
| `LeaveEncashedOnGrosYn` | integer |
| `CostToCompanyYn` | integer |
| `PBYn` | integer |
| `CTCAllowances` | string (comma-separated) |
| `ApplicabilityType` | string |
| `ApplicableOn` | string |
| `AdvanceApplicabilityType` | string |
| `AdvanceApplicableOn` | string |
| `FromDays` | string |
| `ToDays` | string |
| `ActiveYn` | integer |
| `FormulaConfiguredYn` | integer |

---

## 3. EmployeeSalaryStructureApiController

### `storeOrUpdate` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corpId` | `required\|string\|max:10` |
| `puid` | `required\|string\|max:50` |
| `empCode` | `required\|string\|max:20` |
| `companyName` | `required\|string\|max:50` |
| `salaryRevisionMonth` | `required\|string\|max:11` |
| `arrearWithEffectFrom` | `required\|string\|max:11` |
| `payGroup` | `required\|string\|max:20` |
| `ctc` | `required\|string\|max:20` |
| `ctcYearly` | `required\|string\|max:20` |
| `monthlyBasic` | `required\|string\|max:20` |
| `leaveEnchashOnGross` | `required\|string\|max:20` |
| `grossList` | `required\|string` |
| `year` | `required\|string\|max:4` |
| `increment` | `nullable\|string\|max:20` |

**Additional fields from Model $fillable (via `$request->all()`):**
| Field | Type |
|---|---|
| `performanceBonus` | string |
| `otherAlowances` | string (JSON) |
| `otherBenifits` | string (JSON) |
| `recurringDeductions` | string (JSON) |
| `aplb` | string |

### `salaryRevisionProcess` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corpId` | `required\|string\|max:10` |
| `companyName` | `required\|string\|max:100` |
| `empCode` | `required\|string\|max:20` |
| `year` | `required\|string\|max:4` |
| `salaryRevisionMonth` | `required\|string\|max:11` |
| `arrearWithEffectFrom` | `required\|string\|max:11` |
| `increment` | `required\|numeric\|min:0` |

---

## 4. EmployeePayrollSalaryProcessApiController

### `storeOrUpdate` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corpId` | `required\|string\|max:10` |
| `empCode` | `required\|string\|max:20` |
| `companyName` | `required\|string\|max:100` |
| `year` | `required\|string\|max:4` |
| `month` | `required\|string\|max:30` |
| `grossList` | `required` |
| `status` | `required\|string` |
| `isShownToEmployeeYn` | `required\|integer` |

**Additional fields from Model $fillable (via `$request->all()`):**
| Field | Type |
|---|---|
| `otherAllowances` | string (JSON) |
| `otherBenefits` | string (JSON) |
| `recurringDeduction` | string (JSON) |

### `bulkProcessFromSalaryStructures` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corpId` | `required\|string\|max:10` |
| `companyName` | `nullable\|string\|max:100` |
| `year` | `required\|string\|max:4` |
| `month` | `required\|string\|max:50` |
| `status` | `required\|string` |
| `isShownToEmployeeYn` | `required\|integer` |

### `exportPayrollExcel` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corpId` | `required\|string\|max:10` |
| `companyName` | `required\|string\|max:100` |
| `year` | `required\|string\|max:4` |
| `month` | `required\|string\|max:50` |
| `subBranch` | `nullable\|string\|max:100` |

---

## 5. CompanyShiftPolicyApiController

### `store` (POST)
**Validation rules + `$request->only()`:**
| Field | Rule |
|---|---|
| `corp_id` | `required\|string` |
| `company_name` | `required\|string` |
| `shift_code` | `required\|string` |

---

## 6. LeaveRequestApiController

### `store` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corp_id` | `required\|string` |
| `company_name` | `required\|string` |
| `empcode` | `required\|string` |
| `from_date` | `required\|date` |
| `to_date` | `required\|date\|after_or_equal:from_date` |
| `reason` | `nullable\|string` |
| `leave_reason_description` | `nullable\|string` |

*Note: `puid`, `full_name`, `emp_designation`, `status` are auto-generated server-side.*

### `updateStatus` (PUT) `/{id}`
**Validation rules:**
| Field | Rule |
|---|---|
| `corp_id` | `required\|string` |
| `empcode` | `required\|string` |
| `status` | `required\|string\|in:Approved,Rejected,Returned` |
| `reject_reason` | `required_if:status,Rejected\|nullable\|string` |

---

## 7. AttendanceApiController

### `checkInOrOut` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `puid` | `required\|string` |
| `corpId` | `required\|string` |
| `userName` | `required\|string` |
| `empCode` | `required\|string` |
| `companyName` | `required\|string` |
| `time` | `required\|string` |
| `Lat` | `nullable\|string` |
| `Long` | `nullable\|string` |
| `Address` | `nullable\|string` |

### `bulkInsertAttendance` (POST)
**Validation rules (array of records):**
| Field | Rule |
|---|---|
| `records` | `required\|array` |
| `records.*.corpId` | `required\|string` |
| `records.*.userName` | `required\|string` |
| `records.*.empCode` | `required\|string` |
| `records.*.companyName` | `required\|string` |
| `records.*.checkIn` | `nullable\|string` |
| `records.*.checkOut` | `nullable\|string` |
| `records.*.date` | `required\|string` |
| `records.*.Lat` | `nullable\|string` |
| `records.*.Long` | `nullable\|string` |
| `records.*.Address` | `nullable\|string` |

### `fetchMonthlyAttendance` (GET/POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corpId` | `required\|string` |
| `userName` | `nullable\|string` |
| `empCode` | `nullable\|string` |
| `companyName` | `nullable\|string` |
| `month` | `nullable\|integer\|min:1\|max:12` |
| `year` | `nullable\|integer\|min:2000\|max:2100` |

---

## 8. EmployeeAttendanceSummaryApiController

### `bulkInsertAttendanceSummary` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corpId` | `required\|string\|max:10` |
| `companyName` | `required\|string\|max:100` |
| `month` | `required\|string\|max:30` |
| `year` | `required\|string\|max:4` |

### `update` (PUT) `/{id}`
**Validation rules:**
| Field | Rule |
|---|---|
| `corpId` | `sometimes\|string\|max:10` |
| `empCode` | `sometimes\|string\|max:20` |
| `companyName` | `sometimes\|string\|max:100` |
| `totalPresent` | `sometimes\|integer\|min:0` |
| `workingDays` | `sometimes\|integer\|min:0` |
| `holidays` | `sometimes\|integer\|min:0` |
| `weekOff` | `sometimes\|integer\|min:0` |
| `leave` | `sometimes\|numeric\|min:0` |
| `month` | `sometimes\|string\|max:30` |
| `year` | `sometimes\|string\|max:4` |

*Uses `$request->only()` with: corpId, empCode, companyName, totalPresent, workingDays, holidays, weekOff, leave, month, year*

---

## 9. HolidayListApiController

### `storeOrUpdate` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corpId` | `required\|string\|max:10` |
| `puid` | `required\|string\|max:50` |
| `companyNames` | `required\|string\|max:255` |
| `country` | `required\|string\|max:50` |
| `state` | `required\|string\|max:50` |
| `city` | `required\|string\|max:50` |
| `holidayName` | `required\|string\|max:255` |
| `holidayDate` | `required\|date` |
| `year` | `required\|string\|max:4` |
| `holidayType` | `required\|string\|max:100` |
| `recurringType` | `required\|string\|max:50` |

---

## 10. ProfessionalTaxController

### `addProfessionalTax` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corpId` | `required\|string` |
| `companyName` | `required\|string` |
| `state` | `required\|string` |
| `minIncome` | `nullable\|string` |
| `maxIncome` | `nullable\|string` |
| `aboveIncome` | `nullable\|string` |
| `taxAmount` | `nullable\|string` |

### `editProfessionalTax` (PUT) `/{id}`
**Validation rules:**
| Field | Rule |
|---|---|
| `corpId` | `sometimes\|required\|string` |
| `companyName` | `sometimes\|required\|string` |
| `state` | `sometimes\|required\|string` |
| `minIncome` | `nullable\|string` |
| `maxIncome` | `nullable\|string` |
| `aboveIncome` | `nullable\|string` |
| `taxAmount` | `nullable\|string` |

---

## 11. EsiController

### `addEsi` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corpId` | `required\|string` |
| `companyName` | `required\|string` |
| `state` | `required\|string` |
| `incomeRange` | `nullable\|string` |
| `esiAmount` | `nullable\|string` |

### `editEsi` (PUT) `/{id}`
**Validation rules:**
| Field | Rule |
|---|---|
| `corpId` | `sometimes\|required\|string` |
| `companyName` | `sometimes\|required\|string` |
| `state` | `sometimes\|required\|string` |
| `incomeRange` | `nullable\|string` |
| `esiAmount` | `nullable\|string` |

---

## 12. NewsFeedController

### `store` (POST) — Create news feed
**Validation rules:**
| Field | Rule |
|---|---|
| `corpId` | `required\|string\|max:10` |
| `EmpCode` | `required\|string\|max:20` |
| `companyName` | `required\|string\|max:100` |
| `body` | `required\|string` |
| `date` | `required\|string\|max:20` |
| `time` | `required\|string\|max:20` |

*Note: `employeeFullName` and `puid` are auto-generated.*

### `storeComment` (POST) — Add comment
**Validation rules:**
| Field | Rule |
|---|---|
| `corpId` | `required\|string\|max:10` |
| `puid` | `required\|string\|max:100` |
| `EmpCode` | `required\|string\|max:20` |
| `companyName` | `required\|string\|max:100` |
| `comment` | `required\|string` |
| `date` | `required\|string\|max:20` |
| `time` | `required\|string\|max:20` |

### `storeReview` (POST) — Add/Update review (legacy)
**Validation rules:**
| Field | Rule |
|---|---|
| `corpId` | `required\|string\|max:10` |
| `puid` | `required\|string\|max:100` |
| `EmpCode` | `required\|string\|max:20` |
| `companyName` | `required\|string\|max:100` |
| `isLiked` | `nullable\|string\|in:0,1` |
| `comment` | `nullable\|string` |
| `date` | `required\|string\|max:20` |
| `time` | `required\|string\|max:20` |

---

## 13. PaymentBankApiController

### `store` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corp_id` | `required\|string` |
| `bank_name` | `required\|string` |
| `account_no` | `required\|string` |

**Additional fields from Model $fillable (via `$request->all()`):**
| Field | Type |
|---|---|
| `alias_name` | string |
| `challan_report_format` | string |
| `challan_report` | string |
| `transaction_type` | string |
| `branch_name` | string |
| `bsr_code` | string |
| `ifsc_code` | string |
| `micr_code` | string |
| `iban_no` | string |
| `location` | string |
| `address` | string |
| `activeyn` | integer |

### `update` (PUT) `/{corp_id}/{id}`
Same fields as store — uses `$request->all()`.

---

## 14. FormulaBuilderApiController

### `storeOrUpdate` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corpId` | `required\|string` |
| `puid` | `required\|string` |
| `paygroupPuid` | `required\|string` |
| `componentGroupName` | `required\|string` |
| `componentName` | `required\|string` |
| `componentNameRefersTo` | `required\|string` |
| `referenceValue` | `nullable\|string` |
| `formula` | `required\|string` |

*Note: `formula` is processed (lowercased, spaces removed) before saving.*

---

## 15. ComponentTypeApiController

### `storeOrUpdate` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corpId` | `required\|string` |
| `componentType` | `required\|string` |

---

## 16. EmployeeDetailApiController

### `store` (POST)
**No explicit validation** — uses `$request->all()`. Fields from Model $fillable:
| Field | Type |
|---|---|
| `corp_id` | string (required for logic) |
| `EmpCode` | string (required for logic) |
| `prefix` | string |
| `FirstName` | string |
| `MiddleName` | string |
| `LastName` | string |
| `MaritalStatus` | string |
| `DOB` | string |
| `Gender` | string |
| `BloodGroup` | string |
| `Nationality` | string |
| `WorkEmail` | string |
| `Mobile` | string |
| `SkillType` | string |
| `Pan` | string |
| `Adhaar` | string |
| `Passport` | string |
| `PassportExpiryDate` | string |
| `PersonalEmail` | string |
| `EmgContactName` | string |
| `EmgNumber` | string |
| `EmgContactRelation` | string |
| `PmntAddress` | string |
| `PmntCountry` | string |
| `PmntState` | string |
| `PmntCity` | string |
| `PmntPincode` | string |
| `CrntAddress` | string |
| `CrntCountry` | string |
| `CrntState` | string |
| `CrntCity` | string |
| `SameAsPmntAddYN` | integer |
| `DraftYN` | integer |

### `update` (PUT) `/{corp_id}/{EmpCode}/{id}`
Same fields — uses `$request->all()`.

---

## 17. EmploymentDetailApiController

### `store` (POST)
**No explicit validation** — uses `$request->all()`. Fields from Model $fillable:
| Field | Type |
|---|---|
| `corp_id` | string (required for logic) |
| `company_name` | string |
| `dateOfJoining` | string |
| `EmpCode` | string (required for logic) |
| `BiometricId` | string |
| `BusinessUnit` | string |
| `Department` | string |
| `SubDepartment` | string |
| `Designation` | string |
| `Region` | string |
| `Branch` | string |
| `SubBranch` | string |
| `EmploymentType` | string |
| `EmploymentStatus` | string |
| `ConfirmationStatus` | string |
| `ReportingManager` | string |
| `FunctionalManager` | string |
| `ReportingManager3` | string |
| `PFNumber` | string |
| `UAN` | string |
| `EmployeeContributionLimit` | string |
| `EmployerContributionLimit` | string |
| `PensionNumber` | string |
| `PF` | string |
| `Gratuity` | string |
| `DraftYN` | integer |
| `ActiveYn` | integer (defaults to 1 if not provided) |

### `update` (PUT) `/{corp_id}/{EmpCode}`
Same fields — uses `$request->all()`.

---

## 18. EmployeeBankDetailApiController

### `store` (POST)
**No explicit validation** — uses `$request->all()`. Fields from Model $fillable:
| Field | Type |
|---|---|
| `corp_id` | string |
| `empcode` | string |
| `SlryPayMode` | string |
| `SlryBankName` | string |
| `SlryBranchName` | string |
| `SlryIFSCCode` | string |
| `SlryAcntNo` | string |
| `RimbPayMode` | string |
| `RimbBankName` | string |
| `RimbBranchName` | string |
| `RimbIFSCCode` | string |
| `RimbAcntNo` | string |
| `same_as_salary_yn` | integer |

### `update` (PUT) `/{corp_id}/{empcode}`
Same fields — uses `$request->all()`.

---

## 19. EmployeeStatutoryDetailApiController

### `store` (POST)
**No explicit validation** — uses `$request->all()`. Fields from Model $fillable:
| Field | Type | Default if empty |
|---|---|---|
| `corp_id` | string | — |
| `EmpCode` | string | — |
| `TaxRegime` | string | "N/A" |
| `AdhaarPanLinkedYN` | integer | — |
| `ProvidentFundYN` | integer | — |
| `PFNo` | string | "N/A" |
| `UAN` | string | "N/A" |
| `PensionYN` | integer | — |
| `PensionNo` | string | "N/A" |
| `EmpStateInsuranceYN` | integer | — |
| `EmpStateInsNo` | string | "N/A" |
| `EmpStateInsDispensaryName` | string | "N/A" |
| `ESISubUnitCode` | string | "N/A" |
| `LabourWelfareFundYN` | integer | — |
| `PTYN` | integer | — |
| `BonusYN` | integer | — |
| `GratuityYN` | integer | — |
| `GratuityInCtcYN` | integer | — |
| `DateOfJoin` | string | "N/A" |
| `VoluntaryPfYN` | integer | — |
| `VoluntaryPFAmount` | string | "N/A" |
| `VoluntaryPFPercent` | string | "N/A" |
| `VoluntaryPFEffectiveDate` | string | "N/A" |
| `EmployerCtbnToNPSYN` | integer | — |
| `EmployerAmount` | string | "N/A" |
| `EmployerPercentage` | string | "N/A" |
| `EmployerPanNumber` | string | "N/A" |
| `SalaryMode` | string | "N/A" |
| `SalaryBank` | string | "N/A" |
| `ReimbursementMode` | string | "N/A" |
| `ReimbursementBank` | string | "N/A" |
| `DraftYN` | integer | Always set to 0 |

### `update` (PUT) `/{corp_id}/{EmpCode}/{id}`
Same fields except `EmpPFContbtnLmt` & `EmployerPFContbtnLmt` are excluded.

---

## 20. FamilyDetailApiController

### `store` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corp_id` | `required\|string` |
| `EmpCode` | `required\|string` |

**Additional fields from Model $fillable (via `$request->all()`):**
| Field | Type |
|---|---|
| `FatherName` | string |
| `FatherDOB` | string |
| `MotherName` | string |
| `MotherDob` | string |
| `MaritalStatus` | string |
| `SpuseName` | string |
| `SpouseDob` | string |
| `MarriageDate` | string |
| `DependentName` | string |
| `DependentRelation` | string |
| `DependentDob` | string |
| `DependentGender` | string |
| `DependentRemarks` | string |

### `update` (PUT) `/{corp_id}/{EmpCode}`
Same fields — uses `$request->all()`.

---

## 21. EmployeeEducationApiController

### `store` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corp_id` | `required\|string` |
| `empcode` | `required\|string` |
| `Degree` | `required\|string` |
| `Type` | `required\|string` |
| `FromYear` | `required\|string` |
| `ToYear` | `required\|string` |
| `Specialization` | `nullable\|string` (defaults to "N/A") |
| `University` | `nullable\|string` (defaults to "N/A") |
| `Institute` | `nullable\|string` (defaults to "N/A") |
| `Grade` | `nullable\|string` (defaults to "N/A") |

### `update` (PUT) `/{corp_id}/{empcode}/{id}`
Same fields — uses `$request->all()`.

---

## 22. EmployeeWorkExperienceApiController

### `store` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corp_id` | `required\|string` |
| `empcode` | `required\|string` |
| `CompanyName` | `required\|string` |
| `Designation` | `required\|string` |
| `FromDate` | `required\|string` |
| `ToDate` | `required\|string` |

*Uses `$request->all()` — additional fields from model may also be accepted.*

### `update` (PUT) `/{corp_id}/{empcode}/{id}`
Same fields — uses `$request->all()`.

---

## 23. EmployeeNomineeDetailApiController

### `store` (POST)
**No explicit validation** — uses `$request->all()`. Fields from Model $fillable:
| Field | Type |
|---|---|
| `corp_id` | string |
| `empcode` | string |
| `statutory_type` | string |
| `nominee_name` | string |
| `relation` | string |
| `dob` | string |
| `gender` | string |
| `share_percent` | string |
| `contact_no` | string |
| `addr` | string |
| `remarks` | string |
| `minor_yn` | integer |

*Note: `color` is auto-generated (random light hex color).*

### `update` (PUT) `/{corp_id}/{empcode}/{id}`
Same fields — uses `$request->all()`.

---

## 24. EmployeeInsurancePolicyApiController

### `store` (POST)
**No explicit validation** — uses `$request->all()`. Fields from Model $fillable:
| Field | Type |
|---|---|
| `corp_id` | string |
| `empcode` | string |
| `name` | string |
| `relationship` | string |
| `dob` | string |
| `gender` | string |
| `policy_no` | string |
| `insurance_type` | string |
| `assured_sum` | string |
| `premium` | string |
| `issue_date` | string |
| `valid_upto` | string |

*Note: `color` is auto-generated (random light hex color).*

### `update` (PUT) `/{corp_id}/{empcode}/{id}`
Same fields — uses `$request->all()`.

---

## 25. CompanyDetailsApiController

### `register` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corp_id` | `required\|string` |
| `company_name` | `required\|string` |
| `company_logo` | `nullable\|file\|mimes:jpg,jpeg,png,gif,svg\|max:2048` |
| `registered_address` | `required\|string` |
| `pin` | `required\|string` |
| `country` | `required\|string` |
| `state` | `required\|string` |
| `city` | `required\|string` |
| `phone` | `required\|string` |
| `fax` | `nullable\|string` |
| `currency` | `required\|string` |
| `contact_person` | `required\|string` |
| `industry` | `required\|string` |
| `signatory_name` | `required\|string` |
| `gstin` | `required\|string` |
| `fcbk_url` | `nullable\|string` |
| `youtube_url` | `nullable\|string` |
| `twiter_url` | `nullable\|string` |
| `insta_url` | `nullable\|string` |
| `active_yn` | `boolean` |

### `update` (PUT) `/{company_id}/{corp_id}`
**Validation rules:**
| Field | Rule |
|---|---|
| `company_name` | `sometimes\|required\|string` |
| `company_logo` | `nullable\|file\|mimes:jpg,jpeg,png,gif,svg\|max:2048` |
| `registered_address` | `sometimes\|required\|string` |
| `pin` | `sometimes\|required\|string` |
| `country` | `sometimes\|required\|string` |
| `state` | `sometimes\|required\|string` |
| `city` | `sometimes\|required\|string` |
| `phone` | `sometimes\|required\|string` |
| `fax` | `nullable\|string` |
| `currency` | `sometimes\|required\|string` |
| `contact_person` | `sometimes\|required\|string` |
| `industry` | `sometimes\|required\|string` |
| `signatory_name` | `sometimes\|required\|string` |
| `gstin` | `sometimes\|required\|string` |
| `fcbk_url` | `nullable\|string` |
| `youtube_url` | `nullable\|string` |
| `twiter_url` | `nullable\|string` |
| `insta_url` | `nullable\|string` |
| `active_yn` | `boolean` |

---

## 26. DepartmentApiController

### `store` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corp_id` | `required\|string` |
| `department_name` | `required\|string` |
| `active_yn` | `boolean` |

### `update` (PUT) `/{department_id}`
**Validation rules:**
| Field | Rule |
|---|---|
| `department_name` | `sometimes\|required\|string` |
| `active_yn` | `boolean` |

---

## 27. DesignationApiController

### `store` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corp_id` | `required\|string` |
| `designation_name` | `required\|string` |

### `update` (PUT) `/{id}/{corp_id}`
**Accepted fields:** `designation_name` only (via `$request->only()`).

---

## 28. BranchApiController

### `store` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corp_id` | `required\|string` |
| `branch` | `required\|string` |

### `update` (PUT) `/{corp_id}/{id}`
**Accepted fields:** `branch` only (via `$request->only()`).

---

## 29. LocationApiController

### `addCountry` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `country_name` | `required\|string` |
| `corp_id` | `required\|string` |

### `addState` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `state_name` | `required\|string` |
| `country_id` | `required\|integer` |
| `corp_id` | `required\|string` |

### `addCity` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `city_name` | `required\|string` |
| `country_id` | `required\|integer` |
| `state_id` | `required\|integer` |
| `corp_id` | `required\|string` |

---

## 30. BankApiController

### `store` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corp_id` | `required\|string` |
| `bank_name` | `required\|string` |

*Uses `$request->all()` — additional model fields may be accepted.*

---

## 31. RecruitmentJobPostingApiController

### `store` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corp_id` | `required\|string` |
| `job_title` | `required\|string\|max:255` |

**Additional fields from Model $fillable (via `$request->all()`):**
| Field | Type |
|---|---|
| `department` | string |
| `sub_department` | string |
| `designation` | string |
| `location` | string |
| `employment_type` | string |
| `no_of_openings` | integer |
| `job_description` | text |
| `requirements` | text |
| `min_salary` | decimal |
| `max_salary` | decimal |
| `currency` | string |
| `application_deadline` | date |
| `status` | string |
| `created_by` | string |

### `update` (PUT) `/{corp_id}/{id}`
Same fields — uses `$request->all()`.

### `changeStatus` (PUT) `/{corp_id}/{id}/status`
| Field | Rule |
|---|---|
| `status` | `required\|in:Open,Closed,On Hold` |

---

## 32. RecruitmentCandidateApiController

### `store` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corp_id` | `required\|string` |
| `first_name` | `required\|string\|max:255` |
| `email` | `nullable\|email` |
| `resume` | `nullable\|file\|mimes:pdf,doc,docx\|max:5120` |

**Additional fields from Model $fillable (via `$request->except('resume')`):**
| Field | Type |
|---|---|
| `last_name` | string |
| `phone` | string |
| `dob` | date |
| `gender` | string |
| `current_location` | string |
| `highest_qualification` | string |
| `total_experience_years` | decimal |
| `current_ctc` | decimal |
| `expected_ctc` | decimal |
| `notice_period` | string |
| `linkedin_url` | string |
| `source` | string |
| `referred_by` | string |
| `skills` | text |
| `status` | string |

### `update` (PUT) `/{corp_id}/{id}`
Same fields — uses `$request->except('resume')`.

---

## 33. RecruitmentStageApiController

### `store` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corp_id` | `required\|string` |
| `stage_name` | `required\|string\|max:255` |

**Additional fields from Model $fillable (via `$request->all()`):**
| Field | Type |
|---|---|
| `stage_order` | integer |
| `stage_type` | string |
| `description` | text |
| `is_active` | boolean |

### `update` (PUT) `/{corp_id}/{id}`
Same fields — uses `$request->all()`.

---

## 34. RecruitmentApplicationApiController

### `store` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corp_id` | `required\|string` |
| `job_posting_id` | `required\|integer\|exists:recruitment_job_postings,id` |
| `candidate_id` | `required\|integer\|exists:recruitment_candidates,id` |

**Additional fields from Model $fillable (via `$request->all()`):**
| Field | Type |
|---|---|
| `applied_date` | date (defaults to today) |
| `current_stage` | string |
| `status` | string |
| `overall_remarks` | text |

### `update` (PUT) `/{corp_id}/{id}`
All model fillable fields via `$request->all()`.

### `decideCandidate` (POST) `/{corp_id}/{id}/decide`
**Validation rules:**
| Field | Rule |
|---|---|
| `final_decision` | `required\|in:Selected,Rejected` |
| `decided_by` | `required\|string` |
| `overall_remarks` | `nullable\|string` |

### `addStageResult` (POST) `/{corp_id}/{application_id}/stage-results`
**Validation rules:**
| Field | Rule |
|---|---|
| `stage_id` | `required\|integer\|exists:recruitment_stages,id` |
| `outcome` | `nullable\|in:Pass,Fail,On Hold,No Show` |
| `rating` | `nullable\|integer\|min:1\|max:10` |

**Additional fields from Model $fillable (via `$request->all()`):**
| Field | Type |
|---|---|
| `stage_name` | string |
| `scheduled_at` | datetime |
| `conducted_at` | datetime |
| `interviewer_emp_code` | string |
| `interviewer_name` | string |
| `remarks` | text |

---

## 35. OfferLetterApiController

### `generate` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corp_id` | `required\|string` |
| `application_id` | `required\|integer\|exists:recruitment_applications,id` |
| `template_id` | `required\|integer\|exists:offer_letter_templates,id` |
| `date_of_joining` | `required\|date` |
| `ctc_annual` | `required\|numeric\|min:0` |
| `generated_by` | `nullable\|string` |

**Additional optional fields:**
| Field | Type |
|---|---|
| `candidate_name` | string (auto-filled from candidate record if omitted) |
| `designation` | string (auto-filled from job posting if omitted) |
| `department` | string (auto-filled from job posting if omitted) |
| `location` | string (auto-filled from job posting if omitted) |

### `updateStatus` (PUT) `/{corp_id}/{id}/status`
| Field | Rule |
|---|---|
| `status` | `required\|in:Draft,Sent,Accepted,Declined,Revoked` |

---

## 36. OfferLetterTemplateApiController

### `store` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corp_id` | `required\|string` |
| `template_name` | `required\|string\|max:255` |
| `company_logo` | `nullable\|file\|mimes:png,jpg,jpeg,svg\|max:2048` |
| `digital_signature` | `nullable\|file\|mimes:png,jpg,jpeg\|max:2048` |
| `salary_components` | `nullable\|string` (JSON string) |

**Additional fields from Model $fillable (via `$request->except(...)`):**
| Field | Type |
|---|---|
| `header_content` | text |
| `body_content` | text |
| `footer_content` | text |
| `signatory_name` | string |
| `signatory_designation` | string |
| `salary_currency` | string |
| `salary_notes` | text |
| `is_active` | boolean |
| `created_by` | string |

### `update` (PUT) `/{corp_id}/{id}`
Same fields.

### `uploadLogo` (POST) `/{corp_id}/{id}/logo`
| Field | Rule |
|---|---|
| `company_logo` | `required\|file\|mimes:png,jpg,jpeg,svg\|max:2048` |

### `uploadSignature` (POST) `/{corp_id}/{id}/signature`
| Field | Rule |
|---|---|
| `digital_signature` | `required\|file\|mimes:png,jpg,jpeg\|max:2048` |

---

## 37. EmployeeListApiController

### `index` (GET)
**Query parameters (validated):**
| Field | Rule |
|---|---|
| `corp_id` | `required\|string` |
| `company_name` | `required\|string` |

### `getTodaysBirthdays` (GET)
**Query parameters (validated):**
| Field | Rule |
|---|---|
| `corp_id` | `required\|string` |
| `company_name` | `nullable\|string` |
| `empcode` | `required\|string` |

### `getLeaveSummary` (GET)
**Query parameters (validated):**
| Field | Rule |
|---|---|
| `corp_id` | `required\|string` |
| `company_name` | `required\|string` |
| `empcode` | `required\|string` |

---

## 38. ShiftPolicyApiController

### `store` (POST)
**No explicit validation** — uses `$request->all()`. Fields from Model $fillable:
| Field | Type | Default if empty |
|---|---|---|
| `puid` | string | — |
| `corp_id` | string | — |
| `shift_code` | string | — |
| `shift_name` | string | — |
| `shift_start_time` | string | "00.00AM" |
| `first_half` | string | "00.00AM" |
| `second_half` | string | "00.00AM" |
| `checkin` | string | "00.00AM" |
| `gracetime_early` | string | "00.00AM" |
| `gracetime_late` | string | "00.00AM" |
| `absence_halfday` | string | "00.00AM" |
| `absence_fullday` | string | "00.00AM" |
| `absence_halfday_absent_aftr` | string | "00.00AM" |
| `absence_fullday_absent_aftr` | string | "00.00AM" |
| `absence_secondhalf_absent_chckout_before` | string | "00.00AM" |
| `absence_shiftallowance_yn` | integer | 0 |
| `absence_restrict_manager_backdate_yn` | integer | 0 |
| `absence_restrict_hr_backdate_yn` | integer | 0 |
| `absence_restrict_manager_future` | integer | 0 |
| `absence_restrict_hr_future` | integer | 0 |
| `adv_settings_sihft_break_deduction_yn` | integer | 0 |
| `adv_settings_deduct_time_before_shift_yn` | integer | 0 |
| `adv_settings_restrict_work_aftr_cutoff_yn` | integer | 0 |
| `adv_settings_visible_in_wrkplan_rqst_yn` | integer | 0 |
| `define_weekly_off_yn` | integer | 0 |

### `update` (PUT) `/{corp_id}/{puid}`
Same fields.

---

## 39. CheckinPolicyApiController

### `store` (POST)
**Validation rules (partial):**
| Field | Rule |
|---|---|
| `puid` | `required\|string` |
| `corp_id` | `required\|string` |
| `policy_name` | `required\|string` |

**All additional fields from `$request->all()` (defaults to "N/A" if empty):**
`applicability_type`, `applicability_for`, `advnc_applicability_type`, `advnc_applicability_for`

**All additional fields from `$request->all()` (defaults to 0 if empty):**
`web_checkin_yn`, `punches_yn`, `punches_no`, `restrict_emp_marking_attndc_yn`, `punch_start_time`, `punch_end_time`, `IP_validation_yn`, `from_ip`, `to_ip`, `web_chckin_rqst_frm_whatsapp_yn`, `web_chckin_rqst_frm_teams_yn`, `mobile_chckin_yn`, `photo_attdnc_yn`, `no_of_photos`, `location_attdnc_yn`, `adv_location_tracking_yn`, `specific_period_wrk_location_approval_yn`, `location_approval_days_limit`, `max_punches_allowed_yn`, `punches_no_allowed`, `restrict_emp_attndc_yn`, `attdnc_regularization_yn`, `emp_bck_dated_attdnc_regularization_yn`, `emp_bck_dated_attdnc_regularization_days`, `mngr_bck_dated_regularization_yn`, `mngr_bck_dated_regularization_days`, `hr_bck_dated_attdnc_regularization_yn`, `hr_bck_dated_attdnc_regularization_days`, `attdnc_regularization_limit_type`, `attdnc_regularization_total_limit`, `ar_aftr_attdnc_process_yn`, `future_dtd_attdnc_regularization_yn`, `atleast_one_punch_attdnc_regularization_yn`, `for_ar_attachment_yn`, `whatsapp_ar_rqst_yn`, `teams_ar_rqst_yn`, `ar_week_off_emp_restricted_yn`, `ar_holidays_emp_restricted_yn`, `on_duty_yn`, `emp_bck_dtd_onduty_rqst_yn`, `emp_bck_dtd_onduty_rqst_days`, `mngr_bck_dtd_onduty_rqst_yn`, `mngr_bck_dtd_onduty_rqst_days`, `hr_bck_dtd_onduty_rqst_yn`, `hr_bck_dtd_onduty_rqst_days`, `configure_overall_onduty_limit_yn`, `project_log_time_yn`, `raise_onduty_aftr_attdnc_process_yn`, `future_dtd_onduty_yn`, `restrict_manager_onduty_beyond_limit_yn`, `restrict_hr_onduty_beyond_limit_yn`, `attchmnt_for_od_yn`, `whatsapp_od_rqst_yn`, `teams_od_rqst_yn`, `onduty_week_off_emp_restricted_yn`, `onduty_holidays_emp_restricted_yn`, `from_days`, `to_days`

### `update` (PUT) `/{puid}`
Same fields.

---

## 40. LeavePolicyApiController

### `store` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `corpid` | `required\|string` |
| `puid` | `required\|string` |
| `policyName` | `required\|string` |
| `leaveType` | `required\|string` |
| `applicabilityType` | `required\|string` |
| `applicabilityOn` | `required\|string` |
| `advanceApplicabilityType` | `nullable\|string` (defaults to "N/A") |
| `advanceApplicabilityOn` | `nullable\|string` (defaults to "N/A") |
| `fromDays` | `nullable\|string` (defaults to "0") |
| `toDays` | `nullable\|string` (defaults to "0") |

### `update` (PUT) `/{puid}`
Same fields — uses `$request->all()`.

---

## 41. LeaveTypeBasicConfigurationApiController

### `store` (POST)
**Validation rules:**
| Field | Rule |
|---|---|
| `puid` | `required\|string` |
| `corpid` | `required\|string` |
| `leaveCode` | `required\|string` |
| `leaveName` | `required\|string` |
| `leaveCycleStartMonth` | `required\|string` |
| `leaveCycleEndMonth` | `required\|string` |
| `leaveTypeTobeCredited` | `required\|string` |
| `LimitDays` | `required\|string` |
| `LeaveType` | `required\|string` |
| `encahsmentAllowedYN` | `required\|integer` |
| `isConfigurationCompletedYN` | `required\|integer` |

### `update` (PUT) `/{puid}`
Same fields — uses `$request->all()`.

---

## 42. LeaveTypeFullConfigurationApiController

### `store` (POST)
**No explicit validation** — uses `$request->all()`. All fields from Model $fillable:
| Field | Type |
|---|---|
| `puid` | string |
| `corpid` | string |
| `applicabilityType` | string |
| `applicabledEmployeeStatus` | string |
| `applicabledGender` | string |
| `leaveCreditedType` | string |
| `allowAdditionalLeaveOnJoin` | string |
| `roundOffCreditedLeaves` | string |
| `lapseLeaveYn` | integer |
| `creditLeaveIfResignationPendingYn` | integer |
| `empHalfDayLeaveRqstYN` | integer |
| `empLeaveRqstYN` | integer |
| `empMaxRqstTenureType` | string |
| `empMaxNoRqstTenure` | string |
| `empMaxRqstYearType` | string |
| `empMaxNoRqstYear` | string |
| `empMaxRqstMonthType` | string |
| `empMaxNoRqstMonth` | string |
| `empMinLeaveRequiredToRqstType` | string |
| `empMinNoLeaveRequiredToRqst` | string |
| `maxNoContiniousLeaveAllowedType` | string |
| `maxNoContionousLeaveAllowedNo` | string |
| `maxNoLeavesYearlyType` | string |
| `maxNoLeavesYearly` | string |
| `maxNoLeavesMonthlyType` | string |
| `maxNoLeavesMonthly` | string |
| `empBackDtdLeaveYn` | integer |
| `empBackDtdLeaveNo` | string |
| `minDaysAdvncLeaveRqstType` | string |
| `minDaysAdvncLeaveRqstNo` | string |
| `empFutureDtdLeaveYn` | integer |
| `mngrFutureDtdLeaveYn` | integer |
| `hrFutureDtdLeaveYn` | integer |
| `mngrBackDtdLeaveYn` | integer |
| `mngrBackDtdLeaveNo` | string |
| `hrBackDtdLeaveYn` | integer |
| `hrBackDtdLeaveNo` | string |
| `leaveApplctnDocRequiredYn` | integer |
| `docRequiredForLeavesNoAbove` | string |
| `raiseLeaveRqstAftrAttdncProcessedYn` | integer |
| `restrictLeaveRqstOnPendingResignationYn` | integer |
| `restrictLeaveRqstAftrJoiningForSpecicPeriodType` | string |
| `restrictLeaveRqstAftrJoiningForSpecicPeriodDaysNo` | string |
| `excludeAbsentDaysYn` | integer |
| `specificEmpStatusLeaveApplicableYn` | integer |
| `empStatusProbationYn` | integer |
| `empStatusConfirmedYn` | integer |
| `empStatusResignedYn` | integer |
| `leaveRqstApplicableType` | string |
| `applicableToGenderYn` | integer |
| `applicabilityMaleYn` | integer |
| `applicabilityFemaleYn` | integer |
| `applicabilityOtherYn` | integer |
| `enableThisLeaveBirthdayYn` | integer |
| `birthdayLeaveAdvanceDays` | string |
| `birthdayLeavePostDays` | string |
| `weddingAnniversaryLeaveYn` | integer |
| `weedingAnniversaryLeaveAdvanceDays` | string |
| `weddingAnniversaryLeavePostDays` | string |
| `advanceLeaveBalanceYn` | integer |
| `advanceBalanceLimit` | string |
| `blockClubbingWithOtherLeavesYn` | integer |
| `leaveTypes` | string |
| `leaveDonationYn` | integer |
| `donateLeaveEmpYn` | integer |
| `donateLeaveManagerYn` | integer |
| `donateLeaveHrYn` | integer |
| `maxAnnualLeaveDonation` | string |
| `maxCarryForwardLeavesType` | string |
| `maxCarryForwardLeavesBalance` | string |
| `carryForwardMethod` | string |
| `carryForwardMethodDays` | string |
| `nextYearBalanceUsageOfCrntYearYn` | integer |
| `nextYearBalanceUsageOfCrntYearLimit` | string |
| `backdatedLeaveCancellationAfterCarryForwardYn` | integer |
| `allowBackdatedLeaveAfterCarryForward` | string |
| `sendCarryForwardAlertYn` | integer |
| `noOfDaysToSendAlertBeforeExpiry` | string |
| `alertMessage` | string |
| `includeWeeklyOffAsLeaveYn` | integer |
| `includePostLeaveWeeklyOffYn` | integer |
| `sandwichLeaveYn` | integer |
| `requireAdvanceSubmissionToExcludeWeeklyOffType` | string |
| `advanceSubmissionDaysBeforeToExcludeWeeklyOff` | string |
| `holidaysInLeaveCountYn` | integer |
| `includeWeekoffAndHolidayIfSpannedByLeaveYn` | integer |
| `requireAdvanceSubmissionToExcludeWeekoffAndHolidayType` | string |
| `advanceSubmissionDaysBeforeToExcludeWeekoffAndHoliday` | string |

### `update` (PUT) `/{puid}`
Same fields — uses `$request->all()`.

---

*End of extraction — 42 controllers, all store/update methods, all accepted fields.*
