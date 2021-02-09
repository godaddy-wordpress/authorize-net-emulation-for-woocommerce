# Summary

_Include a brief high level summary: this is what the PR contains_

### Story: [CH 1234](story-url)
### Release: #1234 (release PR)

## UI Changes

_If this PR changes any UI elements, add relevant screenshots and a brief summary of each as needed_

- _This was changed_ [screenshot URL]
- _This was also changed_ [screenshot URL]

## QA

_If applicable, add specific steps for the reviewer to perform as part of their QA process prior to approving this pull request. Steps should be in a step -> success? format, like below_

### Setup

_List any configuration requirements for testing_

- This setting is configured
- Taxes are enabled

### Steps

1. Enable debug mode
1. Place a test order
    - [ ] The transaction request and response is logged
1. Enable the "Both" debug mode
1. Place a test order
    - [ ] The request and response is logged
    - [ ] The same data is printed on the checkout page as a notice

## Before merge

- [ ] This PR makes the appropriate modifications to automated tests or explains why none are needed
- [ ] This PR makes the appropriate modifications to documentation or explains why none are needed
- [ ] This change does not impact usage or expected outputs of covered code
