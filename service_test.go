package service

import (
	"encoding/json"
	"os"
	"path/filepath"
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

	solrserveruri := "http://localhost:8983"
	solrcorename := "kassiscore"

	SolrClearDocument(solrserveruri, solrcorename)

	files := []string{"nonono"}
	err := Fileimport(files)
	//TODO: errメッセージを確認したい（os: Unable to open file ～）
	if err == nil {
		t.Fatal("failed test")
	}

	dir, _ := os.Getwd()
	filepathname := filepath.Join(dir, "testdata", "Book1.xlsx")

	files = []string{filepathname}
	err = Fileimport(files)
	if err != nil {
		t.Fatal("failed test")
	}

	res, err := SolrQuery(solrserveruri, solrcorename)
	if err != nil {
		t.Fatal("failed test")
	}
	if res.Data.NumFound != 8 {
		t.Fatal("failed test")
	}

	var materials []*Material

	fBytes, err := res.Data.Docs.ToBytes()
	if err != nil {
		t.Fatal(err)
	}
	err = json.Unmarshal(fBytes, &materials)
	if err != nil {
		t.Fatal(err)
	}

	if materials[1].ID != "/home/tmpz84/dev/kassis/src/testdata/Book1.xlsxデータ1" {
		t.Fatal("failed test")
	}
	if materials[1].Foldername != "/home/tmpz84/dev/kassis/src/testdata" {
		t.Fatal("failed test")
	}
	if materials[1].Filename != "Book1.xlsx" {
		t.Fatal("failed test")
	}
	if materials[1].Cellvalues[0] != "2022A1" {
		t.Fatal("failed test")
	}

	SolrClearDocument(solrserveruri, solrcorename)

	filepathname = filepath.Join(dir, "testdata", "shinanogawa.pdf")

	files = []string{filepathname}
	err = Fileimport(files)
	if err != nil {
		t.Fatal("failed test")
	}
	//TODO: 登録されたデータの内容などを確認したい
	res, err = SolrQuery(solrserveruri, solrcorename)
	if err != nil {
		t.Fatal("failed test")
	}
	if res.Data.NumFound != 5 {
		t.Fatal("failed test")
	}

	SolrClearDocument(solrserveruri, solrcorename)

	dir, _ = os.Getwd()
	filepathname1 := filepath.Join(dir, "testdata", "Book1.xlsx")
	filepathname2 := filepath.Join(dir, "testdata", "shinanogawa.pdf")

	files = []string{filepathname1, filepathname2}
	err = Fileimport(files)
	if err != nil {
		t.Fatal("failed test")
	}
	res, err = SolrQuery(solrserveruri, solrcorename)
	if err != nil {
		t.Fatal("failed test")
	}
	if res.Data.NumFound != 13 {
		t.Fatal("failed test")
	}

}
