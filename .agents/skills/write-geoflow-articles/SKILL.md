---
name: write-geoflow-articles
description: Create, revise, or review GEOFlow article titles, content briefs, Markdown articles, channel variants, and reusable AI prompts as evidence-based GEO content. Use when the user asks to write an article, generate or optimize titles, turn knowledge-base material into content, adapt an article for a publishing channel, or check an article before GEOFlow publication. Apply automatically to article-writing work inside the GEOFlow project. Do not publish or distribute content unless the user separately authorizes that operation.
---

# Write GEOFlow Articles

## Work from a concrete brief

1. Identify the target question, audience, market, language, channel, article type, and desired action.
2. State material assumptions. Ask only when a missing choice would substantially change the article; otherwise proceed and label placeholders or evidence gaps.
3. Separate verified facts, reasonable analysis, and unsupported claims. Never turn an unsupported claim into a fact.
4. For time-sensitive facts, verify current primary sources before drafting when browsing is available. Record the source date.
5. Read [writing-standard.md](references/writing-standard.md) before drafting or reviewing an article.

## Prepare the evidence base

- Prefer the user's approved materials and the selected GEOFlow knowledge bases.
- Normalize the brand name, aliases, one-sentence definition, products, audience, limitations, and prohibited claims.
- Keep units, comparison scope, source, and verification date with every important number.
- Say that evidence is unavailable when it is unavailable. Do not invent citations, customers, prices, awards, rankings, capabilities, or quotations.
- Distinguish the author's own analysis from externally sourced facts.

## Select the article contract

- `definition`: explain what something is and its boundaries.
- `comparison`: compare every option on the same dimensions and disclose the subject brand's limitations.
- `ranking`: publish the method, dimensions, weights, evidence, and conflicts before the ranking.
- `how-to`: define prerequisites, numbered steps, expected results, and failure conditions.
- `decision`: answer price, selection, alternatives, suitability, or another buyer-intent question.

Use one primary user question per article. Preserve a user-supplied title unless asked to optimize it; when optimization would materially help, offer alternatives without silently replacing it.

## Draft for GEOFlow

- Treat the page as an evidence page, not an opinion essay.
- Put the direct answer in the opening paragraph.
- Do not include a Markdown H1 in the body because GEOFlow renders the article title as the page H1.
- Use descriptive H2 sections. Aim for 6 or more sections and 8–10 for a full guide when the subject supports them.
- Make important sections independently understandable: open with the answer, then add evidence and explanation.
- Include, when relevant, a definition, 2–5 sourced numeric facts, a same-basis comparison table, numbered steps, suitability and limitations, FAQ, and real references.
- Use FAQ only for query coverage; do not substitute a list of shallow Q&A for the article.
- Avoid generic introductions, keyword stuffing, repetitive conclusions, invented certainty, and marketing superlatives.
- Output clean Markdown suitable for the GEOFlow article body.

## Adapt by market and channel

- Use original Simplified Chinese phrasing for mainland-China audiences and original English phrasing for global audiences. Do not treat machine translation as market adaptation.
- Keep the official-site version comprehensive and authoritative.
- Rewrite, rather than duplicate, variants for WeChat, Zhihu or professional communities, encyclopedia entries, and other external platforms.
- Keep facts consistent across variants while changing the framing, length, and format for the channel.
- Treat the official site as the canonical fact source; do not promise that any platform will cite it.

## Deliver the result

Unless the user asks for another format, return:

1. Material assumptions or missing evidence, only when relevant.
2. Five title candidates with target intent, followed by one recommendation, when title creation is in scope.
3. The final Markdown article or requested revision.
4. A short fact-check list containing unresolved claims, stale numbers, or missing sources.
5. A publication checklist covering summary, keywords, meta description, author, dates, links, and channel variant needs.

Do not pad a simple request to satisfy this output contract. For title-only work, return titles and a concise rationale. For review-only work, preserve the author's voice and report prioritized corrections before any optional rewrite.

## Keep publishing separate

Writing and approval are not publication. Do not create tasks, change review status, publish, distribute, sync, or modify a live site without a separate explicit request and the applicable GEOFlow operations workflow.
