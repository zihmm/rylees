from dataclasses import dataclass
from typing import TypedDict, Literal

class ProjectConfig(TypedDict):
    id: str
    name: str
    key: str
    description: str
    customer_name: str
    customer_industry: str
    llm_temperature: float
    llm_tonality: str

@dataclass
class AnalysisResult:
    diff: str
    commit_messages: list[str]

class PublishPayload(TypedDict):
    startRef: str
    endRef: str
    type: Literal["commits", "tag"]
    branchName: str
    body: str
    versionBump: Literal["major", "minor", "patch"]

class PublishResponse(TypedDict):
    id: str
    status: str
    version: str
