package main

import (
	"github.com/stretchr/testify/assert"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"testing"
)

func TestHelloHandler(t *testing.T) {
	cwd, _ := os.Getwd()
	homedir := filepath.Join(cwd, "..", "..")

	router := NewRouter(homedir)

	req := httptest.NewRequest("GET", "/", nil)
	rec := httptest.NewRecorder()

	router.ServeHTTP(rec, req)

	assert.Equal(t, http.StatusOK, rec.Code)
	assert.Contains(t, rec.Body.String(), "Hello, World!!")
}

func TestMaterialsQueryHandler(t *testing.T) {
	cwd, _ := os.Getwd()
	homedir := filepath.Join(cwd, "..", "..")
	router := NewRouter(homedir)

	req := httptest.NewRequest("GET", "/materials", nil)
	rec := httptest.NewRecorder()

	params := req.URL.Query()
	params.Add("q", "ぽっぽ焼き")
	req.URL.RawQuery = params.Encode()

	router.ServeHTTP(rec, req)

	assert.Equal(t, http.StatusOK, rec.Code)

	//fmt.Println(rec.Body.String())

	assert.Equal(t, "2022A1", rec.Body.String())
}
