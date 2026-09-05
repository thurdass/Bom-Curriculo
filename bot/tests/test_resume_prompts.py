import json

import pytest

from app.models.resume_analysis import BuiltResumeResult, ResumeScoreResult
from app.services.ai.resume_builder_prompt import build_resume_construction_prompt
from app.services.ai.resume_score_prompt import build_resume_score_prompt


@pytest.mark.parametrize(
    "prompt,schema,functional_instruction",
    [
        (
            build_resume_construction_prompt("Resume source"),
            BuiltResumeResult,
            "never invent, infer, or fabricate a fact",
        ),
        (
            build_resume_score_prompt("Resume source"),
            ResumeScoreResult,
            "Do not rewrite, restructure, or extract the resume",
        ),
    ],
)
def test_prompt_relies_on_structured_output_without_repeating_json_schema(
    prompt, schema, functional_instruction
) -> None:
    serialized_schema = json.dumps(schema.model_json_schema(), ensure_ascii=False)

    assert serialized_schema not in prompt
    assert '"$defs"' not in prompt
    assert '"properties"' not in prompt
    assert functional_instruction in prompt
    assert "Return only the structured JSON response" in prompt
