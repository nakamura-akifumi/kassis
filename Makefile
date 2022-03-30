NAME := kassis
VERSION := $(gobump show -r)
REVISION := $(shell git rev-parse --short HEAD)
LDFLAGS := -X 'main.revision=$(REVISION)'
BIN_DIR := ./bin
MAKE_DIR_NAME := $(notdir $(abspath .))
TARGETS := $(shell ls '*.go')

export GO111MODULE=on

.PHONY: deps
deps:
	go mod tidy

# 必要なツール類をセットアップする
.PHONY: devel-deps
devel-deps: deps
	go install golang.org/x/lint/golint@latest
	go install github.com/x-motemen/gobump/cmd/gobump@latest
	go install github.com/Songmu/make2help/cmd/make2help@latest

.PHONY: test
test: deps
	go test ./...

.PHONY: build
build:
	go build -o $(BIN_DIR)/$(MAKE_DIR_NAME) $(TARGETS)

## Lint
.PHONY: lint
lint: devel-deps
	go vet ./...
	golint --set_exit_status ./...
