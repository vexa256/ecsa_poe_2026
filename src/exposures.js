window.EXPOSURES = {
    metadata: {
        dataset_name: "poe_secondary_screening_exposure_risks",
        schema_version: "1.0.0",
        context: "WHO IHR 2005 aligned exposure risk factors for POE secondary screening",
        last_updated: "2026-03-26",
        usage: "Used for structured exposure history assessment during secondary screening",
        notes: [
            "All exposures are non-diagnostic and support syndromic risk assessment",
            "Designed for POE use — no clinical or laboratory assumptions",
            "Each exposure is boolean with optional notes field in implementation"
        ]
    },

    exposures: [
        {
            code: "TRAVEL_OUTBREAK_AREA",
            label: "Travel to area with known outbreak",
            description: "Travel within the past 14–21 days to a geographic area with an ongoing outbreak or known public health event",
            category: "travel_related",
            risk_level: "high",
            requires_details: true
        },
        {
            code: "CONTACT_SICK_PERSON",
            label: "Contact with a sick individual",
            description: "Close contact with a person exhibiting symptoms of infectious illness",
            category: "contact_exposure",
            risk_level: "moderate",
            requires_details: true
        },
        {
            code: "CONTACT_CONFIRMED_CASE",
            label: "Contact with a confirmed or suspected case",
            description: "Direct or indirect contact with an individual classified as a suspected or confirmed case of an infectious disease",
            category: "contact_exposure",
            risk_level: "high",
            requires_details: true
        },
        {
            code: "CONTACT_DEAD_BODY",
            label: "Contact with a dead body (funeral exposure)",
            description: "Participation in burial rituals or physical contact with a deceased individual, particularly in areas with known infectious disease risk",
            category: "high_risk_exposure",
            risk_level: "high",
            requires_details: true
        },
        {
            code: "HEALTHCARE_EXPOSURE",
            label: "Exposure in healthcare setting",
            description: "Presence in or exposure to a healthcare environment where infectious patients are treated",
            category: "occupational_exposure",
            risk_level: "moderate",
            requires_details: true
        },
        {
            code: "ANIMAL_EXPOSURE",
            label: "Contact with animals (wildlife/livestock)",
            description: "Direct or indirect contact with animals, including wildlife or livestock, that may serve as reservoirs for zoonotic diseases",
            category: "zoonotic_exposure",
            risk_level: "moderate",
            requires_details: true
        },
        {
            code: "UNSAFE_FOOD_WATER",
            label: "Consumption of unsafe food or water",
            description: "Ingestion of food or water that may be contaminated or not prepared under safe hygienic conditions",
            category: "environmental_exposure",
            risk_level: "moderate",
            requires_details: false
        },
        {
            code: "VECTOR_EXPOSURE",
            label: "Presence in high vector transmission areas (e.g., mosquitoes)",
            description: "Stay in or travel through areas with high vector activity associated with vector-borne diseases",
            category: "vector_exposure",
            risk_level: "moderate",
            requires_details: false
        },
        {
            code: "MASS_GATHERING",
            label: "Attendance at mass gatherings",
            description: "Participation in events involving large crowds where disease transmission risk may be elevated",
            category: "community_exposure",
            risk_level: "moderate",
            requires_details: true
        }
    ]
}