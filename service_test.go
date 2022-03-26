package kassiscore

import (
	"encoding/json"
	"os"
	"path/filepath"
	"testing"
)

func TestExtnameToMediaTypeSuccess(t *testing.T) {
	result := ExtnameToMediaType("bar")
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

func TestImportFromFile(t *testing.T) {

	solrserveruri := "http://localhost:8983"
	solrcorename := "kassiscore"

	SolrClearDocument(solrserveruri, solrcorename)

	files := []string{"nonono"}
	err := importFromFile(files)
	//TODO: errメッセージを確認したい（os: Unable to open file ～）
	if err == nil {
		t.Fatal("failed test")
	}

	dir, _ := os.Getwd()
	filepathname := filepath.Join(dir, "testdata", "Book1.xlsx")

	files = []string{filepathname}
	err = importFromFile(files)
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

	dir, _ = os.Getwd()
	filepathname = filepath.Join(dir, "testdata", "Book1.xlsx")
	targetid := filepathname + "データ1"
	if materials[1].ID != targetid {
		t.Fatal("failed test")
	}

	filepathname = filepath.Join(dir, "testdata")
	targetfolder := filepathname
	if materials[1].Foldername != targetfolder {
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
	err = importFromFile(files)
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
	err = importFromFile(files)
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
