# ADR-005: Implementation of the Backend API as a Modular Monolith

---

## Status

Accepted

## Context

The Backend API serves as the central application core of the system. It is responsible for customer management, project management, release note management, authentication, authorization and persistence.

Although these domains represent distinct business capabilities, they share a common data model, are developed by the same team and are deployed as a single application. The expected system size and usage patterns do not justify the operational complexity of a distributed architecture.

Several alternatives were considered:

### Alternative A: Traditional Monolith

All functionality is implemented within a single application without explicit module boundaries.

**Advantages**

- Simple implementation
- Single deployment unit
- Minimal infrastructure requirements

**Disadvantages**

- Weak separation of concerns
- Increased risk of architectural erosion
- Difficult to maintain as the system grows
- Reduced clarity of domain responsibilities

### Alternative B: Microservice Architecture

Each domain is implemented as an independent service.

**Advantages**

- Independent deployment and scaling
- Strong isolation between domains
- Clear service ownership

**Disadvantages**

- Increased operational complexity
- More complex testing and debugging
- Additional infrastructure requirements
- Higher communication overhead
- Complexity not justified by project scope

### Alternative C: Modular Monolith

Business domains are implemented as independent modules within a single deployable application.

**Advantages**

- Clear domain boundaries
- Low operational complexity
- Simplified development and testing
- Easier future extraction of services

**Disadvantages**

- Modules are still deployed together
- Requires architectural discipline to maintain boundaries

## Decision

The Backend API shall be implemented as a modular monolith.

Business capabilities are organized into clearly separated modules, including:

- Authentication and Authorization
- Customer Management
- Project Management
- Release Note Management
- Publication Management

Each module encapsulates its own business logic and exposes well-defined interfaces to other modules.

The application is deployed as a single runtime unit while maintaining explicit architectural boundaries between domains.

## Consequences

### Positive Consequences

- Clear separation of business responsibilities
- Improved maintainability through modularization
- Lower operational complexity compared to microservices
- Simplified deployment and monitoring
- Easier testing and debugging
- Supports future extraction of modules into independent services if required

### Negative Consequences

- Entire application must be deployed together
- Independent scaling of individual modules is not possible
- Architectural boundaries must be actively enforced
- Risk of unintended coupling between modules if governance is neglected

### Impact on Quality Goals

| Quality Goal | Contribution |
|--------------|-------------|
| Maintainability | Clear modular structure and separation of concerns |
| Extensibility | New functionality can be added within dedicated modules |
| Testability | Modules can be tested independently |
| Simplicity | Avoids unnecessary distributed-system complexity |
| Future-proofing | Enables gradual evolution towards services if needed |

## Governance

The following governance rules apply:

- All business functionality shall belong to a clearly defined module.
- Modules shall communicate only through explicit interfaces.
- Direct access to internal implementation details of other modules is prohibited.
- Shared functionality shall be placed in dedicated shared components rather than duplicated across modules.
- Database access shall be encapsulated within the owning module.
- New features shall be assigned to an existing module or introduced through a new module with a clearly defined responsibility.
- Any future transition to a microservice architecture requires a dedicated ADR and an assessment of its impact on the established quality goals.

This ADR establishes the modular monolith as the foundational architectural style for the Backend API, balancing maintainability, simplicity and future extensibility.

## Notes

- **Approval date:** 2026-06-01