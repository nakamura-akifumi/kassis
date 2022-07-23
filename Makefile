# バージョン
VERSION:=$(shell cat VERSION)
# リビジョン
REVISION:=$(shell git rev-parse --short HEAD 2> /dev/null || cat REVISION)

#
GOVERSION=$(shell go version)
GOOS=$(shell go env GOOS)
GOARCH=$(shell go env GOARCH)

# 出力先のディレクトリ
BINDIR:=bin

# ext
ifeq ($(OS),Windows_NT)
	BIN_EXT:=.exe
else
	BIN_EXT:=
endif

ifdef RELEASE
	GO_BUILD_TAGS:=release
endif

# build tags
GO_BUILD_TAGS:=debug
ifdef RELEASE
	GO_BUILD_TAGS:=release
endif
# race detector
GO_BUILD_RACE:=-race
ifdef RELEASE
	GO_BUILD_RACE:=
endif
# static build flag
GO_BUILD_STATIC:=
ifdef RELEASE
	GO_BUILD_STATIC:=-a -installsuffix netgo
	GO_BUILD_TAGS:=$(GO_BUILD_TAGS),netgo
endif

# version ldflag
GO_LDFLAGS_VERSION:=-X '${ROOT_PACKAGE}.VERSION=${VERSION}' -X '${ROOT_PACKAGE}.REVISION=${REVISION}'

# build ldflags
GO_LDFLAGS:=$(GO_LDFLAGS_VERSION) $(GO_LDFLAGS_SYMBOL) $(GO_LDFLAGS_STATIC)

# go build
GO_BUILD:=-tags=$(GO_BUILD_TAGS) $(GO_BUILD_RACE) $(GO_BUILD_STATIC) -ldflags "$(GO_LDFLAGS)"

default: build

all: clean test build_all

build:
	go build $(GO_BUILD) -o ${BINDIR}/configrator.${GOOS}${BIN_EXT} cmd/configrator/main.go
	go build $(GO_BUILD) -o ${BINDIR}/import.${GOOS}${BIN_EXT} cmd/import/main.go
	go build $(GO_BUILD) -o ${BINDIR}/webserver.${GOOS}${BIN_EXT} cmd/webserver/main.go

build_all:
	GOARCH=amd64 GOOS=darwin go build $(GO_BUILD) -o ${BINDIR}/configrator-amd64-darwin cmd/configrator/main.go
	GOARCH=amd64 GOOS=linux go build $(GO_BUILD) -o ${BINDIR}/configrator-amd64-linux cmd/configrator/main.go
	GOARCH=amd64 GOOS=window go build $(GO_BUILD) -o ${BINDIR}/configrator-windows.exe cmd/configrator/main.go

	GOARCH=amd64 GOOS=darwin go build $(GO_BUILD) -o ${BINDIR}/import-amd64-darwin cmd/import/main.go
	GOARCH=amd64 GOOS=linux go build $(GO_BUILD) -o ${BINDIR}/import-amd64-linux cmd/import/main.go
	GOARCH=amd64 GOOS=window go build $(GO_BUILD) -o ${BINDIR}/import-windows.exe cmd/import/main.go

	GOARCH=amd64 GOOS=darwin go build $(GO_BUILD) -o ${BINDIR}/webserver-amd64-darwin cmd/webserver/main.go
	GOARCH=amd64 GOOS=linux go build $(GO_BUILD) -o ${BINDIR}/webserver-amd64-linux cmd/webserver/main.go
	GOARCH=amd64 GOOS=window go build $(GO_BUILD) -o ${BINDIR}/webserver-windows.exe cmd/webserver/main.go

clean:
	go clean
	rm ${BINDIR}/*

test:
	go test ./...

test_coverage:
	go test ./... -coverprofile=coverage.out

dep:
	go mod download

vet:
	go vet

lint:
	golangci-lint run --enable-all