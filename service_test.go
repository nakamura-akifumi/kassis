package service

import (
	"testing"
)

func TestExtnameToMediaTypeSuccess(t *testing.T) {
	result := ExtnameToMediaType("hoge")
	if result != "File" {
		t.Fatal("failed test")
	}

	result = ExtnameToMediaType("xlsx")
	if result != "File" {
		t.Fatal("failed test")
	}

	result = ExtnameToMediaType(".xlsx")
	if result != MEDIATYPE_EXCEL {
		t.Fatal("failed test")
	}
}

func TestFileimport(t *testing.T) {
	files := []string{"nonono"}
	err := Fileimport(files)
	if err != nil {
		t.Fatal("failed test")
	}

}